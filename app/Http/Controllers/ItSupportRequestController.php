<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalDecision;
use App\Enums\ItSupportRequestStatus;
use App\Http\Requests\AssignItSupportRequestRequest;
use App\Http\Requests\DecideItSupportRequestApprovalRequest;
use App\Http\Requests\StoreItSupportRequestCommentRequest;
use App\Http\Requests\StoreItSupportRequestRequest;
use App\Mail\ItSupportRequestReceived;
use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestAttachment;
use App\Models\ItSupportRequestComment;
use App\Models\RequestType;
use App\Models\User;
use App\Services\ItSupportRequestAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ItSupportRequestController extends Controller
{
    public function create(): View
    {
        return view('it-support-requests.create');
    }

    public function index(): View
    {
        $this->authorize('viewAny', ItSupportRequest::class);

        return view('it-support-requests.index');
    }

    public function manage(): View
    {
        Gate::authorize('manageAssignments', ItSupportRequest::class);

        return view('it-support-requests.manage');
    }

    public function approvals(): View
    {
        Gate::authorize('viewApprovalQueue', ItSupportRequest::class);

        return view('it-support-requests.approvals');
    }

    public function assigned(): View
    {
        Gate::authorize('viewAssigned', ItSupportRequest::class);

        return view('it-support-requests.assigned');
    }

    public function assign(
        AssignItSupportRequestRequest $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $technicianId = $request->validated('technician_id');

        $technician = DB::transaction(function () use ($itSupportRequest, $technicianId, $request, $auditLogger) {
            $lockedRequest = ItSupportRequest::query()
                ->whereKey($itSupportRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('assign', $lockedRequest);

            $fromStatus = $lockedRequest->status;
            $previousTechnicianId = $lockedRequest->assigned_technician_id;
            $technician = User::technicians()->findOrFail($technicianId);
            $lockedRequest->technician()->associate($technician);
            $lockedRequest->assigned_at = now();
            $lockedRequest->status = ItSupportRequestStatus::Assigned;
            $lockedRequest->save();
            $auditLogger->record(
                $lockedRequest,
                $request->user(),
                $previousTechnicianId ? 'reassigned' : 'assigned',
                $fromStatus,
                ItSupportRequestStatus::Assigned,
                $previousTechnicianId
                    ? "Reassigned from technician user #{$previousTechnicianId} to {$technician->full_name}."
                    : "Assigned to {$technician->full_name}.",
            );

            return $technician;
        });

        return redirect()
            ->route('edp.it-support-requests.index')
            ->with('status', "Request #{$itSupportRequest->getKey()} assigned to {$technician->full_name}.");
    }

    public function decideApproval(
        DecideItSupportRequestApprovalRequest $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $decision = ApprovalDecision::from($request->validated('decision'));
        $user = $request->user();

        DB::transaction(function () use ($itSupportRequest, $decision, $request, $user, $auditLogger) {
            $lockedRequest = ItSupportRequest::query()
                ->whereKey($itSupportRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('approve', $lockedRequest);

            $fromStatus = $lockedRequest->status;
            $lockedRequest->approvals()->create([
                'approver_user_id' => $user->getKey(),
                'approver_name' => $user->full_name,
                'approver_role' => $user->role->value,
                'decision' => $decision,
                'comment' => $request->validated('comment'),
                'decided_at' => now(),
            ]);
            $lockedRequest->status = $decision === ApprovalDecision::Approved
                ? ItSupportRequestStatus::Approved
                : ItSupportRequestStatus::Rejected;
            $lockedRequest->save();
            $auditLogger->record(
                $lockedRequest,
                $user,
                'approval_decision',
                $fromStatus,
                $lockedRequest->status,
                trim($decision->value.': '.(string) $request->validated('comment')),
            );
        });

        return redirect()->route('it-support-requests.show', $itSupportRequest);
    }

    public function show(ItSupportRequest $itSupportRequest): View
    {
        $this->authorize('view', $itSupportRequest);
        $canViewInternalNotes = Gate::allows('viewInternalNotes', $itSupportRequest);
        $itSupportRequest->load([
            'comments' => fn ($query) => $query
                ->when(! $canViewInternalNotes, fn ($comments) => $comments->where('is_internal', false))
                ->with('author'),
            'detailsRows',
            'attachments',
            'resolver',
            'technician',
            'approvals',
        ]);

        return view('it-support-requests.show', [
            'supportRequest' => $itSupportRequest,
            'canViewInternalNotes' => $canViewInternalNotes,
        ]);
    }

    public function startWork(
        Request $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger): RedirectResponse {
            $lockedRequest = ItSupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('startWork', $lockedRequest);
            $fromStatus = $lockedRequest->status;

            $lockedRequest->status = ItSupportRequestStatus::InProgress;
            $lockedRequest->started_at = now();
            $lockedRequest->save();
            $auditLogger->record($lockedRequest, $request->user(), 'work_started', $fromStatus, $lockedRequest->status);

            return redirect()->route('it-support-requests.show', $lockedRequest);
        });
    }

    public function resolve(
        Request $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger): RedirectResponse {
            $lockedRequest = ItSupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('resolve', $lockedRequest);
            $fromStatus = $lockedRequest->status;

            $lockedRequest->status = ItSupportRequestStatus::Resolved;
            $lockedRequest->resolved_by_user_id = $request->user()->getKey();
            $lockedRequest->resolved_at = now();
            $lockedRequest->save();
            $auditLogger->record($lockedRequest, $request->user(), 'resolved', $fromStatus, $lockedRequest->status);

            return redirect()->route('it-support-requests.show', $lockedRequest);
        });
    }

    public function confirmCompletion(
        Request $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger): RedirectResponse {
            $lockedRequest = ItSupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('confirmCompletion', $lockedRequest);
            $fromStatus = $lockedRequest->status;

            $lockedRequest->status = ItSupportRequestStatus::Closed;
            $lockedRequest->closed_at = now();
            $lockedRequest->requester_confirmed_at = now();
            $lockedRequest->save();
            $auditLogger->record($lockedRequest, $request->user(), 'requester_confirmed', $fromStatus, $lockedRequest->status);

            return redirect()->route('it-support-requests.show', $lockedRequest);
        });
    }

    public function cancel(
        Request $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): RedirectResponse {
            $lockedRequest = ItSupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('cancel', $lockedRequest);
            $fromStatus = $lockedRequest->status;
            $lockedRequest->status = ItSupportRequestStatus::Cancelled;
            $lockedRequest->save();
            $auditLogger->record(
                $lockedRequest,
                $request->user(),
                'cancelled',
                $fromStatus,
                $lockedRequest->status,
                $validated['reason'] ?? null,
            );

            return redirect()->route('it-support-requests.show', $lockedRequest);
        });
    }

    public function reopen(
        Request $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): RedirectResponse {
            $lockedRequest = ItSupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('reopen', $lockedRequest);
            $fromStatus = $lockedRequest->status;
            $lockedRequest->status = ItSupportRequestStatus::Assigned;
            $lockedRequest->closed_at = null;
            $lockedRequest->requester_confirmed_at = null;
            $lockedRequest->resolved_at = null;
            $lockedRequest->resolved_by_user_id = null;
            $lockedRequest->started_at = null;
            $lockedRequest->save();
            $auditLogger->record(
                $lockedRequest,
                $request->user(),
                'reopened',
                $fromStatus,
                $lockedRequest->status,
                $validated['reason'],
            );

            return redirect()->route('it-support-requests.show', $lockedRequest);
        });
    }

    public function storeComment(
        StoreItSupportRequestCommentRequest $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $user = $request->user();
        $proofPath = null;

        try {
            $comment = DB::transaction(function () use ($request, $itSupportRequest, $user, $auditLogger, &$proofPath) {
                if ($request->hasFile('proof')) {
                    $proofPath = $request->file('proof')->store('it-support-requests/proofs', 'private');

                    if ($proofPath === false) {
                        throw new RuntimeException('The proof document could not be stored.');
                    }
                }

                $comment = $itSupportRequest->comments()->create([
                    'user_id' => $user->getKey(),
                    'author_name' => $user->full_name,
                    'body' => $request->validated('body'),
                    'proof_path' => $proofPath,
                    'is_internal' => $request->boolean('is_internal'),
                ]);

                $isInternal = $comment->is_internal;
                $auditLogger->record(
                    $itSupportRequest,
                    $user,
                    $isInternal ? 'internal_note_added' : 'comment_added',
                    $itSupportRequest->status,
                    $itSupportRequest->status,
                    Str::limit($comment->body, 500),
                );

                if ($proofPath) {
                    $auditLogger->record(
                        $itSupportRequest,
                        $user,
                        'attachment_uploaded',
                        $itSupportRequest->status,
                        $itSupportRequest->status,
                        'Discussion proof uploaded: '.basename($proofPath),
                    );
                }

                return $comment;
            });
        } catch (Throwable $exception) {
            if (is_string($proofPath)) {
                Storage::disk('private')->delete($proofPath);
            }

            throw $exception;
        }

        return redirect()->route('it-support-requests.show', $itSupportRequest);
    }

    public function downloadAttachment(ItSupportRequest $itSupportRequest): StreamedResponse
    {
        $this->authorize('view', $itSupportRequest);

        abort_unless($itSupportRequest->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($itSupportRequest->attachment_path), 404);

        $extension = pathinfo($itSupportRequest->attachment_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $itSupportRequest->attachment_path,
            "it-support-request-{$itSupportRequest->getKey()}.{$extension}",
        );
    }

    public function downloadRequestAttachment(
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAttachment $attachment,
    ): StreamedResponse {
        $this->authorize('view', $itSupportRequest);
        abort_unless($attachment->it_support_request_id === $itSupportRequest->getKey(), 404);
        abort_unless(Storage::disk('private')->exists($attachment->file_path), 404);

        return Storage::disk('private')->download(
            $attachment->file_path,
            $attachment->original_name,
        );
    }

    public function downloadProof(
        ItSupportRequest $itSupportRequest,
        ItSupportRequestComment $comment,
    ): StreamedResponse {
        $this->authorize('view', $itSupportRequest);

        abort_unless($comment->it_support_request_id === $itSupportRequest->getKey(), 404);
        abort_if(
            $comment->is_internal && ! Gate::allows('viewInternalNotes', $itSupportRequest),
            404,
        );
        abort_unless($comment->proof_path, 404);
        abort_unless(Storage::disk('private')->exists($comment->proof_path), 404);

        $extension = pathinfo($comment->proof_path, PATHINFO_EXTENSION);

        return Storage::disk('private')->download(
            $comment->proof_path,
            "it-support-request-{$itSupportRequest->getKey()}-proof-{$comment->getKey()}.{$extension}",
        );
    }

    public function store(
        StoreItSupportRequestRequest $request,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $user = $request->user();
        $requestType = RequestType::active()->findOrFail($request->validated('request_type_id'));
        $storedPaths = [];
        $fieldDefinition = collect(config('request_forms.request_types'))
            ->firstWhere('key', $requestType->key);
        abort_unless($fieldDefinition, 500, 'Request type field configuration is missing.');

        try {
            $supportRequest = DB::transaction(function () use ($request, $user, $requestType, $fieldDefinition, $auditLogger, &$storedPaths) {
                $status = $requestType->requires_approval
                    ? ItSupportRequestStatus::PendingApproval
                    : ItSupportRequestStatus::Submitted;
                $supportRequest = $user->itSupportRequests()->create([
                    'request_type_id' => $requestType->getKey(),
                    'summary' => $requestType->name,
                    'requester_name' => $user->full_name,
                    'division' => $user->division->value,
                    'support_type' => $requestType->name,
                    'follow_up_question' => $requestType->follow_up_question,
                    'details' => null,
                    'requested_at' => now(),
                    'certified_at' => now(),
                    'status' => $status,
                    'requires_approval' => $requestType->requires_approval,
                    'approval_roles' => $requestType->approval_roles,
                ]);

                foreach ($fieldDefinition['fields'] as $field) {
                    $supportRequest->detailsRows()->create([
                        'field_key' => $field['key'],
                        'field_label' => $field['label'],
                        'field_value' => $request->validated("answers.{$field['key']}"),
                    ]);
                }

                $auditLogger->record($supportRequest, $user, 'submitted', null, $status);

                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('it-support-requests', 'private');
                    $storedPaths[] = $path;
                    $attachment = $supportRequest->attachments()->create([
                        'uploaded_by' => $user->getKey(),
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                    $auditLogger->record(
                        $supportRequest,
                        $user,
                        'attachment_uploaded',
                        $status,
                        $status,
                        "Uploaded attachment #{$attachment->getKey()}: {$attachment->original_name}",
                    );
                }

                return $supportRequest;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        }

        Mail::to($user->email)->queue(new ItSupportRequestReceived($supportRequest));

        return redirect()->route('it-support-requests.show', $supportRequest);
    }
}
