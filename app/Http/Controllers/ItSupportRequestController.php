<?php

namespace App\Http\Controllers;

use App\Enums\ItSupportRequestStatus;
use App\Http\Requests\StoreItSupportRequestRequest;
use App\Mail\ItSupportRequestReceived;
use App\Models\RequestAttachment;
use App\Models\RequestType;
use App\Models\SupportRequest;
use App\Notifications\RequestClaimed;
use App\Services\ItSupportRequestAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        $this->authorize('viewAny', SupportRequest::class);

        return view('it-support-requests.index');
    }

    public function manage(): View
    {
        Gate::authorize('viewIntake', SupportRequest::class);

        return view('it-support-requests.manage');
    }

    public function assigned(): View
    {
        Gate::authorize(Gate::allows('viewAssignedQueue', SupportRequest::class) ? 'viewAssignedQueue' : 'viewAssigned', SupportRequest::class);

        return view('it-support-requests.assigned');
    }

    public function claim(
        Request $request,
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        Gate::authorize('claimRequests', SupportRequest::class);

        $claimedRequest = DB::transaction(function () use ($itSupportRequest, $request, $auditLogger) {
            $claimedAt = now();
            $claimed = SupportRequest::query()
                ->whereKey($itSupportRequest->getKey())
                ->where('status', ItSupportRequestStatus::Submitted->value)
                ->whereNull('assigned_technician_id')
                ->update([
                    'assigned_technician_id' => $request->user()->getKey(),
                    'assigned_at' => $claimedAt,
                    'status' => ItSupportRequestStatus::Assigned->value,
                    'updated_at' => $claimedAt,
                ]);

            if ($claimed !== 1) {
                return null;
            }

            $claimedRequest = SupportRequest::query()
                ->with(['requester', 'technician'])
                ->findOrFail($itSupportRequest->getKey());
            $auditLogger->record(
                $claimedRequest,
                $request->user(),
                'claimed',
                ItSupportRequestStatus::Submitted,
                ItSupportRequestStatus::Assigned,
                "Claimed by {$request->user()->full_name}.",
            );

            return $claimedRequest;
        });

        if (! $claimedRequest) {
            $currentRequest = $itSupportRequest->fresh('technician');

            if ($currentRequest?->technician) {
                return back()->with('error', "Already claimed by {$currentRequest->technician->full_name}");
            }

            return back()->with('error', 'This request is no longer available to claim.');
        }

        $claimedRequest->requester()->firstOrFail()->notify(new RequestClaimed($claimedRequest));

        return redirect()
            ->route('edp.it-support-requests.index')
            ->with('status', "Ticket {$claimedRequest->ticket_no} claimed.");
    }

    public function release(
        Request $request,
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): void {
            $lockedRequest = SupportRequest::query()
                ->whereKey($itSupportRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorize('release', $lockedRequest);
            $fromStatus = $lockedRequest->status;
            $lockedRequest->assigned_technician_id = null;
            $lockedRequest->assigned_at = null;
            $lockedRequest->started_at = null;
            $lockedRequest->status = ItSupportRequestStatus::Submitted;
            $lockedRequest->save();
            $auditLogger->record(
                $lockedRequest,
                $request->user(),
                'released',
                $fromStatus,
                $lockedRequest->status,
                $validated['reason'],
            );
        });

        return back()->with('status', "Ticket {$itSupportRequest->ticket_no} was released to the queue.");
    }

    public function show(SupportRequest $itSupportRequest): View
    {
        $this->authorize('view', $itSupportRequest);
        $cancellationReason = $itSupportRequest->status === ItSupportRequestStatus::Cancelled
            ? $itSupportRequest->logs()->where('action', 'cancelled')->latest('created_at')->value('remarks')
            : null;
        $itSupportRequest->load([
            'attachments',
            'resolver',
            'technician',
            'requestType',
        ]);
        $definition = collect(config('request_forms.request_types'))
            ->firstWhere('key', $itSupportRequest->requestType?->key);
        $answerFields = collect($definition['fields'] ?? [])
            ->map(fn (array $field): array => [
                'label' => $field['label'],
                'value' => $itSupportRequest->answers[$field['key']] ?? null,
            ])
            ->filter(fn (array $field): bool => filled($field['value']))
            ->values();

        $privateDisk = Storage::disk('private');
        $attachments = $itSupportRequest->attachments
            ->map(function (RequestAttachment $attachment) use ($itSupportRequest, $privateDisk): array {
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

        return view('it-support-requests.show', [
            'supportRequest' => $itSupportRequest,
            'attachments' => $attachments,
            'cancellationReason' => $cancellationReason,
            'answerFields' => $answerFields,
        ]);
    }

    public function startWork(
        Request $request,
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger): RedirectResponse {
            $lockedRequest = SupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
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
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);

        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): RedirectResponse {
            $lockedRequest = SupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('resolve', $lockedRequest);
            $fromStatus = $lockedRequest->status;

            $lockedRequest->status = ItSupportRequestStatus::Resolved;
            $lockedRequest->resolution_notes = $validated['resolution_notes'];
            $lockedRequest->resolved_by_user_id = $request->user()->getKey();
            $lockedRequest->resolved_at = now();
            $lockedRequest->save();
            $auditLogger->record($lockedRequest, $request->user(), 'resolved', $fromStatus, $lockedRequest->status);

            return redirect()->route('it-support-requests.show', $lockedRequest);
        });
    }

    public function hold(
        Request $request,
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): RedirectResponse {
            $lockedRequest = SupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('hold', $lockedRequest);
            $fromStatus = $lockedRequest->status;
            $lockedRequest->status = ItSupportRequestStatus::OnHold;
            $lockedRequest->save();
            $auditLogger->record(
                $lockedRequest,
                $request->user(),
                'put_on_hold',
                $fromStatus,
                $lockedRequest->status,
                $validated['reason'],
            );

            return redirect()->route('it-support-requests.show', $lockedRequest);
        });
    }

    public function resume(
        Request $request,
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): RedirectResponse {
            $lockedRequest = SupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('resume', $lockedRequest);
            $fromStatus = $lockedRequest->status;
            $lockedRequest->status = ItSupportRequestStatus::InProgress;
            $lockedRequest->save();
            $auditLogger->record(
                $lockedRequest,
                $request->user(),
                'resumed',
                $fromStatus,
                $lockedRequest->status,
                $validated['reason'],
            );

            return redirect()->route('it-support-requests.show', $lockedRequest);
        });
    }

    public function confirmCompletion(
        Request $request,
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger): RedirectResponse {
            $lockedRequest = SupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
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
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): void {
            $lockedRequest = SupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
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
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $itSupportRequest, $auditLogger, $validated): RedirectResponse {
            $lockedRequest = SupportRequest::query()->whereKey($itSupportRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('reopen', $lockedRequest);
            $fromStatus = $lockedRequest->status;
            $lockedRequest->status = ItSupportRequestStatus::Assigned;
            $lockedRequest->closed_at = null;
            $lockedRequest->requester_confirmed_at = null;
            $lockedRequest->resolved_at = null;
            $lockedRequest->resolved_by_user_id = null;
            $lockedRequest->started_at = null;
            $lockedRequest->resolution_notes = null;
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

    public function storeProofAttachment(
        Request $request,
        SupportRequest $itSupportRequest,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $user = $request->user();
        $request->validate([
            'proof' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);
        $this->authorize('addProof', $itSupportRequest);
        $proofPath = $request->file('proof')->store('it-support-requests/proofs', 'private');

        if ($proofPath === false) {
            throw new RuntimeException('The proof photo could not be stored.');
        }

        try {
            DB::transaction(function () use ($itSupportRequest, $user, $auditLogger, $request, $proofPath): void {
                $file = $request->file('proof');
                $attachment = $itSupportRequest->attachments()->create([
                    'uploaded_by' => $user->getKey(),
                    'file_path' => $proofPath,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                $auditLogger->record(
                    $itSupportRequest,
                    $user,
                    'attachment_uploaded',
                    $itSupportRequest->status,
                    $itSupportRequest->status,
                    "Uploaded proof photo #{$attachment->getKey()}: {$attachment->original_name}",
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($proofPath);

            throw $exception;
        }

        return redirect()->route('it-support-requests.show', $itSupportRequest);
    }

    public function downloadAttachment(SupportRequest $itSupportRequest): RedirectResponse
    {
        $this->authorize('view', $itSupportRequest);

        $attachment = $itSupportRequest->attachments()->oldest('attachments_id')->firstOrFail();

        return redirect()->route('it-support-requests.attachments.download', [$itSupportRequest, $attachment]);
    }

    public function downloadRequestAttachment(
        SupportRequest $itSupportRequest,
        RequestAttachment $attachment,
    ): StreamedResponse {
        $this->authorize('view', $itSupportRequest);
        abort_unless((string) $attachment->requests_id === (string) $itSupportRequest->getKey(), 404);
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

    public function store(
        StoreItSupportRequestRequest $request,
        ItSupportRequestAuditLogger $auditLogger,
    ): RedirectResponse {
        $user = $request->user();
        $requestType = RequestType::active()->findOrFail($request->validated('request_type_id'));
        $storedPaths = [];
        try {
            $supportRequest = DB::transaction(function () use ($request, $user, $requestType, $auditLogger, &$storedPaths) {
                $status = ItSupportRequestStatus::Submitted;
                $supportRequest = $user->itSupportRequests()->create([
                    'request_types_id' => $requestType->getKey(),
                    'summary' => $request->validated('summary'),
                    'answers' => $request->validated('answers'),
                    'requester_name' => $user->full_name,
                    'division' => $user->division->value,
                    'support_type' => $requestType->name,
                    'follow_up_question' => $requestType->follow_up_question,
                    'requested_at' => now(),
                    'certified_at' => now(),
                    'status' => $status,
                ]);

                $auditLogger->record(
                    $supportRequest,
                    $user,
                    'submitted',
                    null,
                    $status,
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
