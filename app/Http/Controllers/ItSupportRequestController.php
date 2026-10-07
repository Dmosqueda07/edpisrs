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
use App\Models\ItSupportRequestComment;
use App\Models\RequestType;
use App\Models\User;
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

    public function assign(AssignItSupportRequestRequest $request, ItSupportRequest $itSupportRequest): RedirectResponse
    {
        $technicianId = $request->validated('technician_id');

        $technician = DB::transaction(function () use ($itSupportRequest, $technicianId) {
            $lockedRequest = ItSupportRequest::query()
                ->whereKey($itSupportRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('assign', $lockedRequest);

            $technician = User::technicians()->findOrFail($technicianId);
            $lockedRequest->technician()->associate($technician);
            $lockedRequest->assigned_at = now();
            $lockedRequest->status = ItSupportRequestStatus::Assigned;
            $lockedRequest->save();

            return $technician;
        });

        return redirect()
            ->route('edp.it-support-requests.index')
            ->with('status', "Request #{$itSupportRequest->getKey()} assigned to {$technician->full_name}.");
    }

    public function decideApproval(
        DecideItSupportRequestApprovalRequest $request,
        ItSupportRequest $itSupportRequest,
    ): RedirectResponse {
        $decision = ApprovalDecision::from($request->validated('decision'));
        $user = $request->user();

        DB::transaction(function () use ($itSupportRequest, $decision, $request, $user) {
            $lockedRequest = ItSupportRequest::query()
                ->whereKey($itSupportRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('approve', $lockedRequest);

            $lockedRequest->approvals()->create([
                'approver_user_id' => $user->getKey(),
                'approver_name' => $user->full_name,
                'approver_role' => $user->role->value,
                'decision' => $decision,
                'comment' => $request->validated('comment'),
                'decided_at' => now(),
            ]);
            $lockedRequest->status = $decision === ApprovalDecision::Approved
                ? ItSupportRequestStatus::Submitted
                : ItSupportRequestStatus::Rejected;
            $lockedRequest->save();
        });

        return redirect()->route('it-support-requests.show', $itSupportRequest);
    }

    public function show(ItSupportRequest $itSupportRequest): View
    {
        $this->authorize('view', $itSupportRequest);
        $itSupportRequest->load(['comments.author', 'resolver', 'technician', 'approvals']);

        return view('it-support-requests.show', [
            'supportRequest' => $itSupportRequest,
        ]);
    }

    public function startWork(ItSupportRequest $itSupportRequest): RedirectResponse
    {
        $this->authorize('startWork', $itSupportRequest);

        $itSupportRequest->status = ItSupportRequestStatus::InProgress;
        $itSupportRequest->started_at = now();
        $itSupportRequest->save();

        return redirect()->route('it-support-requests.show', $itSupportRequest);
    }

    public function resolve(Request $request, ItSupportRequest $itSupportRequest): RedirectResponse
    {
        $this->authorize('resolve', $itSupportRequest);

        $itSupportRequest->status = ItSupportRequestStatus::Resolved;
        $itSupportRequest->resolved_by_user_id = $request->user()->getKey();
        $itSupportRequest->resolved_at = now();
        $itSupportRequest->save();

        return redirect()->route('it-support-requests.show', $itSupportRequest);
    }

    public function confirmCompletion(ItSupportRequest $itSupportRequest): RedirectResponse
    {
        $this->authorize('confirmCompletion', $itSupportRequest);

        $itSupportRequest->status = ItSupportRequestStatus::Completed;
        $itSupportRequest->completed_at = now();
        $itSupportRequest->save();

        return redirect()->route('it-support-requests.show', $itSupportRequest);
    }

    public function storeComment(
        StoreItSupportRequestCommentRequest $request,
        ItSupportRequest $itSupportRequest,
    ): RedirectResponse {
        $user = $request->user();
        $proofPath = null;

        try {
            $comment = DB::transaction(function () use ($request, $itSupportRequest, $user, &$proofPath) {
                if ($request->hasFile('proof')) {
                    $proofPath = $request->file('proof')->store('it-support-requests/proofs', 'local');

                    if ($proofPath === false) {
                        throw new RuntimeException('The proof document could not be stored.');
                    }
                }

                return $itSupportRequest->comments()->create([
                    'user_id' => $user->getKey(),
                    'author_name' => $user->full_name,
                    'body' => $request->validated('body'),
                    'proof_path' => $proofPath,
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($proofPath)) {
                Storage::disk('local')->delete($proofPath);
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

    public function downloadProof(
        ItSupportRequest $itSupportRequest,
        ItSupportRequestComment $comment,
    ): StreamedResponse {
        $this->authorize('view', $itSupportRequest);

        abort_unless($comment->it_support_request_id === $itSupportRequest->getKey(), 404);
        abort_unless($comment->proof_path, 404);
        abort_unless(Storage::disk('local')->exists($comment->proof_path), 404);

        $extension = pathinfo($comment->proof_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $comment->proof_path,
            "it-support-request-{$itSupportRequest->getKey()}-proof-{$comment->getKey()}.{$extension}",
        );
    }

    public function store(StoreItSupportRequestRequest $request): RedirectResponse
    {
        $user = $request->user();
        $requestType = RequestType::active()->findOrFail($request->validated('request_type_id'));
        $attachmentPath = null;

        try {
            $supportRequest = DB::transaction(function () use ($request, $user, $requestType, &$attachmentPath) {
                if ($request->hasFile('attachment')) {
                    $attachmentPath = $request->file('attachment')->store('it-support-requests', 'local');

                    if ($attachmentPath === false) {
                        throw new RuntimeException('The supporting document could not be stored.');
                    }
                }

                return $user->itSupportRequests()->create([
                    'request_type_id' => $requestType->getKey(),
                    'requester_name' => $user->full_name,
                    'division' => $user->division->value,
                    'support_type' => $requestType->name,
                    'follow_up_question' => $requestType->follow_up_question,
                    'details' => $request->validated('details'),
                    'attachment_path' => $attachmentPath,
                    'certified_at' => now(),
                    'status' => $requestType->requires_approval
                        ? ItSupportRequestStatus::PendingApproval
                        : ItSupportRequestStatus::Submitted,
                    'requires_approval' => $requestType->requires_approval,
                    'approval_roles' => $requestType->approval_roles,
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }

            throw $exception;
        }

        Mail::to($user->email)->queue(new ItSupportRequestReceived($supportRequest));

        return redirect()->route('it-support-requests.show', $supportRequest);
    }
}
