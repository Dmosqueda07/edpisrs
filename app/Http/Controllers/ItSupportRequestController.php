<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalDecision;
use App\Enums\ItSupportRequestPriority;
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
use App\Notifications\RequestAssigned;
use App\Services\ItSupportRequestAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        Gate::authorize('viewAssignedQueue', ItSupportRequest::class);

        return view('it-support-requests.assigned');
    }

    public function assign(
        AssignItSupportRequestRequest $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $technicianId = $request->validated('technician_id');

        [$technician, $requester] = DB::transaction(function () use ($itSupportRequest, $technicianId, $request, $auditLogger) {
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

            return [$technician, $lockedRequest->requester()->firstOrFail()];
        });

        Notification::send(
            collect([$technician, $requester])->unique(fn (User $recipient) => $recipient->getKey()),
            new RequestAssigned($itSupportRequest->fresh(['requestType', 'technician', 'requester'])),
        );

        return redirect()
            ->route('edp.it-support-requests.index')
            ->with('status', "Ticket {$itSupportRequest->ticket_no} assigned to {$technician->full_name}.");
    }

    public function updatePriority(
        Request $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'priority' => ['required', Rule::enum(ItSupportRequestPriority::class)],
        ]);

        DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): void {
            $lockedRequest = ItSupportRequest::query()
                ->whereKey($itSupportRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('manageAssignments', ItSupportRequest::class);

            $oldPriority = $lockedRequest->priority;
            $newPriority = ItSupportRequestPriority::from($validated['priority']);

            if ($oldPriority === $newPriority) {
                return;
            }

            $lockedRequest->priority = $newPriority;
            $lockedRequest->save();
            $auditLogger->record(
                $lockedRequest,
                $request->user(),
                'priority_changed',
                $lockedRequest->status,
                $lockedRequest->status,
                "Priority changed from {$oldPriority->value} to {$newPriority->value}.",
            );
        });

        return back()->with('status', "Priority updated for ticket {$itSupportRequest->ticket_no}.");
    }

    public function decideApproval(
        DecideItSupportRequestApprovalRequest $request,
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $decision = ApprovalDecision::from($request->validated('decision'));
        $user = $request->user();

        if ((string) $itSupportRequest->requester_user_id === (string) $user->getKey()) {
            $auditLogger->record(
                $itSupportRequest,
                $user,
                'approval_blocked_self',
                $itSupportRequest->status,
                $itSupportRequest->status,
                'Blocked self-submission decision attempt: '.$decision->value.'.',
            );

            Gate::authorize('approve', $itSupportRequest);
        }

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
        $cancellationReason = $itSupportRequest->status === ItSupportRequestStatus::Cancelled
            ? $itSupportRequest->logs()->where('action', 'cancelled')->latest('created_at')->value('remarks')
            : null;
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

        $privateDisk = Storage::disk('private');
        $attachments = $itSupportRequest->attachments
            ->map(function (ItSupportRequestAttachment $attachment) use ($itSupportRequest, $privateDisk): array {
                $mimeType = $privateDisk->exists($attachment->file_path)
                    ? ($privateDisk->mimeType($attachment->file_path) ?: $attachment->mime_type)
                    : $attachment->mime_type;

                return [
                    'name' => $attachment->original_name,
                    'size' => $attachment->size,
                    'mime_type' => $mimeType,
                    'is_image' => str_starts_with((string) $mimeType, 'image/'),
                    'url' => route('it-support-requests.attachments.download', [$itSupportRequest, $attachment]),
                ];
            })
            ->values();

        if ($attachments->isEmpty() && $itSupportRequest->attachment_path) {
            $legacyPath = $itSupportRequest->attachment_path;
            if ($privateDisk->exists($legacyPath)) {
                $mimeType = $privateDisk->mimeType($legacyPath);
                $attachments->push([
                    'name' => basename($legacyPath),
                    'size' => $privateDisk->size($legacyPath),
                    'mime_type' => $mimeType,
                    'is_image' => str_starts_with((string) $mimeType, 'image/'),
                    'url' => route('it-support-requests.attachment', $itSupportRequest),
                ]);
            }
        }

        return view('it-support-requests.show', [
            'supportRequest' => $itSupportRequest,
            'canViewInternalNotes' => $canViewInternalNotes,
            'attachments' => $attachments,
            'cancellationReason' => $cancellationReason,
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
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): void {
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

        });

        $message = "Request {$itSupportRequest->ticket_no} was cancelled.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'status' => 'cancelled']);
        }

        return redirect()->route('it-support-requests.show', $itSupportRequest)->with('status', $message);
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
        $disk = Storage::disk('private');
        abort_unless($disk->exists($itSupportRequest->attachment_path), 404);

        $mimeType = $disk->mimeType($itSupportRequest->attachment_path);
        abort_unless($this->isPreviewableAttachmentMimeType($mimeType), 404);

        return $disk->response(
            $itSupportRequest->attachment_path,
            basename($itSupportRequest->attachment_path),
            [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function downloadRequestAttachment(
        ItSupportRequest $itSupportRequest,
        ItSupportRequestAttachment $attachment,
    ): StreamedResponse {
        $this->authorize('view', $itSupportRequest);
        abort_unless((string) $attachment->it_support_request_id === (string) $itSupportRequest->getKey(), 404);
        $disk = Storage::disk('private');
        abort_unless($disk->exists($attachment->file_path), 404);
        $mimeType = $disk->mimeType($attachment->file_path) ?: $attachment->mime_type;
        abort_unless($this->isPreviewableAttachmentMimeType($mimeType), 404);

        return $disk->response(
            $attachment->file_path,
            $attachment->original_name,
            [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    private function isPreviewableAttachmentMimeType(?string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ], true);
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
                    'priority' => $request->validated('priority'),
                    'summary' => $request->validated('summary'),
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

                $auditLogger->record(
                    $supportRequest,
                    $user,
                    'submitted',
                    null,
                    $status,
                    'Priority: '.\Illuminate\Support\Str::headline($supportRequest->priority->value),
                );

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
