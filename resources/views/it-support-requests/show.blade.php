@php
    $displayTitle = filled($supportRequest->summary) ? $supportRequest->summary : $supportRequest->support_type;
    $statusColor = match ($supportRequest->status->value) {
        'submitted', 'pending_approval' => 'bg-amber-100 text-amber-800',
        'in_progress' => 'bg-blue-100 text-blue-800',
        'on_hold' => 'bg-orange-100 text-orange-800',
        'closed', 'resolved' => 'bg-green-100 text-green-800',
        'cancelled', 'rejected' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
    $priorityColor = match ($supportRequest->priority->value) {
        'urgent' => 'bg-red-100 text-red-800',
        'high' => 'bg-amber-100 text-amber-800',
        'low' => 'bg-gray-100 text-gray-700',
        default => 'bg-blue-100 text-blue-800',
    };
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="truncate font-semibold text-2xl text-gray-800 leading-tight">{{ $supportRequest->ticket_no ?? '#'.$supportRequest->getKey() }}</h2>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColor }}">{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}</span>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityColor }}">{{ \Illuminate\Support\Str::headline($supportRequest->priority->value) }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div id="request-detail-metadata" class="hidden" data-ticket="{{ $supportRequest->ticket_no ?? '#'.$supportRequest->getKey() }}" data-summary="{{ $displayTitle }}" data-type="{{ $supportRequest->support_type }}" data-status="{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}" data-status-class="{{ $statusColor }}" data-priority="{{ \Illuminate\Support\Str::headline($supportRequest->priority->value) }}" data-priority-class="{{ $priorityColor }}"></div>
            <div id="request-modal-content" class="space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($cancellationReason)
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="status">
                    <p class="font-semibold">Cancellation reason</p>
                    <p class="mt-1 whitespace-pre-wrap">{{ $cancellationReason }}</p>
                </div>
            @endif
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Type of IT Support Needed</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supportRequest->support_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColor }}">{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Priority</dt>
                        <dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityColor }}">{{ \Illuminate\Support\Str::headline($supportRequest->priority->value) }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Requester</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supportRequest->requester_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Division / Section</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supportRequest->division }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Submitted</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supportRequest->created_at->format('M j, Y, g:i A') }} · Certified</dd>
                    </div>
                    @if (filled($supportRequest->summary) && $supportRequest->summary !== $supportRequest->support_type)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Summary</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ $supportRequest->summary }}</dd>
                        </div>
                    @endif
                    @forelse ($supportRequest->detailsRows as $detail)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">{{ $detail->field_label }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ $detail->field_value }}</dd>
                        </div>
                    @empty
                        @if ($supportRequest->details)
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">{{ $supportRequest->follow_up_question ?? 'Details' }}</dt>
                                <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ $supportRequest->details }}</dd>
                            </div>
                        @endif
                    @endforelse
                </dl>
            </div>

            <section id="request-attachments" class="rounded-lg bg-white p-5 shadow-sm sm:p-6" aria-labelledby="attachments-heading">
                <h2 id="attachments-heading" class="text-lg font-medium text-gray-900">Attachments ({{ $attachments->count() }})</h2>

                @if ($attachments->isEmpty())
                    <p class="mt-3 text-sm text-gray-600">No attachments uploaded.</p>
                @else
                    <div class="mt-4 flex flex-wrap gap-4">
                            @php
                                $imageIndex = 0;
                            @endphp
                        @foreach ($attachments as $attachment)
                            @php
                                $sizeLabel = \Illuminate\Support\Number::fileSize($attachment['size'], 1);
                                $displayName = $attachment['name'];
                            @endphp
                            @if ($attachment['is_image'])
                                @php($currentImageIndex = $imageIndex++)
                                <figure class="w-24">
                                    <button type="button" class="block h-24 w-24 overflow-hidden rounded-md border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        aria-label="Preview {{ $displayName }}" data-request-image-preview data-preview-url="{{ $attachment['url'] }}?display=preview" data-file-name="{{ $displayName }}" data-file-size="{{ $sizeLabel }}" data-image-index="{{ $currentImageIndex }}">
                                        <img src="{{ $attachment['url'] }}?display=thumbnail" alt="{{ $displayName }}" loading="lazy" class="h-full w-full object-cover">
                                    </button>
                                    <figcaption class="mt-2 min-w-0">
                                        <span class="block truncate text-sm font-medium text-gray-800" title="{{ $displayName }}">{{ $displayName }}</span>
                                        <span class="block text-xs text-gray-500">{{ $sizeLabel }}</span>
                                    </figcaption>
                                </figure>
                            @else
                                <a href="{{ $attachment['url'] }}?display=preview" target="_blank" rel="noopener noreferrer" class="flex h-24 w-24 flex-col items-center justify-center overflow-hidden rounded-md border border-blue-200 bg-blue-50 p-2 text-center hover:border-blue-400 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <svg class="h-10 w-10 shrink-0 text-blue-700" viewBox="0 0 40 48" fill="none" aria-hidden="true">
                                        <path d="M7 2h17l10 10v32a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" fill="currentColor" opacity=".12" />
                                        <path d="M24 2v10h10M10 28h20M10 34h20M10 40h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                        <path d="M7 2h17l10 10v32a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" />
                                    </svg>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-blue-900" title="{{ $displayName }}">{{ $displayName }}</span>
                                        <span class="mt-1 block truncate text-xs text-blue-800">PDF · {{ $sizeLabel }}</span>
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif

            </section>

            @if ($supportRequest->requires_approval || $supportRequest->approvals->isNotEmpty())
                <section class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">Sensitive request approval</h2>
                        @if ($supportRequest->status === \App\Enums\ItSupportRequestStatus::PendingApproval)
                            <p class="mt-1 text-sm text-amber-800">This request must be approved before EDP can assign it.</p>
                        @elseif ($supportRequest->status === \App\Enums\ItSupportRequestStatus::Rejected)
                            <p class="mt-1 text-sm text-red-700">This request was rejected and cannot be assigned.</p>
                        @endif
                    </div>

                    @forelse ($supportRequest->approvals as $approval)
                        <article class="border-t border-gray-200 pt-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="font-medium text-gray-900">
                                    {{ $approval->approver_name }} ({{ $approval->approver_role }})
                                    {{ \Illuminate\Support\Str::headline($approval->decision->value) }}
                                </p>
                                <time class="text-xs text-gray-500">{{ $approval->decided_at->toDayDateTimeString() }}</time>
                            </div>
                            @if ($approval->comment)
                                <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700">{{ $approval->comment }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="border-t border-gray-200 pt-4 text-sm text-gray-600">No approval decision has been recorded.</p>
                    @endforelse

                    @can('approve', $supportRequest)
                        <form data-request-footer-action method="POST" action="{{ route('it-support-requests.approval', $supportRequest) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="approval-comment" class="block text-sm font-medium text-gray-700">Decision note (optional)</label>
                                <textarea id="approval-comment" name="comment" rows="3" maxlength="2000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">{{ old('comment') }}</textarea>
                                @error('comment')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" name="decision" value="approved" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">
                                    Approve request
                                </button>
                                <button type="submit" name="decision" value="rejected" class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                    Reject request
                                </button>
                            </div>
                            @error('decision')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </form>
                    @endcan
                </section>
            @endif

            @if ($supportRequest->resolved_at)
                <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-900">
                    Resolved by {{ $supportRequest->resolver?->full_name ?? 'EDP technician' }}
                    on {{ $supportRequest->resolved_at->toDayDateTimeString() }}.
                </div>
            @endif

            @if ($supportRequest->started_at)
                <p class="text-sm text-gray-600">Work started {{ $supportRequest->started_at->toDayDateTimeString() }}.</p>
            @endif

            @can('startWork', $supportRequest)
                <form data-request-footer-action method="POST" action="{{ route('it-support-requests.start', $supportRequest) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        Start work
                    </button>
                </form>
            @endcan

            @can('resolve', $supportRequest)
                <form data-request-footer-action method="POST" action="{{ route('it-support-requests.resolve', $supportRequest) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        Sign off and mark resolved
                    </button>
                </form>
            @endcan

            @can('confirmCompletion', $supportRequest)
                <form data-request-footer-action method="POST" action="{{ route('it-support-requests.complete', $supportRequest) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">
                        Confirm completion
                    </button>
                </form>
            @endcan

            @can('cancel', $supportRequest)
                <form data-request-footer-action data-request-cancel-form method="POST" action="{{ route('it-support-requests.cancel', $supportRequest) }}" class="space-y-2">
                    @csrf
                    @method('PATCH')
                    <label for="cancel-reason" class="block text-sm font-medium text-gray-700">Cancellation reason (optional)</label>
                    <textarea id="cancel-reason" name="reason" rows="2" maxlength="300" class="block w-full rounded-md border-gray-300 shadow-sm">{{ old('reason') }}</textarea>
                    @error('reason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Cancel request</button>
                </form>
            @endcan

            @can('reopen', $supportRequest)
                <form data-request-footer-action method="POST" action="{{ route('it-support-requests.reopen', $supportRequest) }}" class="space-y-2">
                    @csrf
                    @method('PATCH')
                    <label for="reopen-reason" class="block text-sm font-medium text-gray-700">Reason for reopening (required)</label>
                    <textarea id="reopen-reason" name="reason" rows="2" required maxlength="2000" class="block w-full rounded-md border-gray-300 shadow-sm">{{ old('reason') }}</textarea>
                    @error('reason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Reopen request</button>
                </form>
            @endcan

            <section class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                <div>
                    <h2 class="text-lg font-medium text-gray-900">Request discussion</h2>
                    <p class="mt-1 text-sm text-gray-600">Comments and proof are visible to you and the assigned technician.</p>
                </div>

                @forelse ($supportRequest->comments as $comment)
                    <article class="border-t border-gray-200 pt-4">
                        <div class="flex items-baseline justify-between gap-4">
                            <h3 class="font-medium text-gray-900">{{ $comment->author_name }}</h3>
                            <time class="text-xs text-gray-500">{{ $comment->created_at->toDayDateTimeString() }}</time>
                        </div>
                        @if ($comment->is_internal)
                            <p class="mt-2 text-xs font-semibold uppercase text-amber-800">Internal note</p>
                        @endif
                        <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700">{{ $comment->body }}</p>
                        @if ($comment->proof_path)
                            <a href="{{ route('it-support-requests.comment-proof', [$supportRequest, $comment]) }}" class="mt-2 inline-block text-sm font-medium text-blue-700 hover:text-blue-900">
                                Download proof
                            </a>
                        @endif
                    </article>
                @empty
                    <p class="border-t border-gray-200 pt-4 text-sm text-gray-600">No comments yet.</p>
                @endforelse

                @can('comment', $supportRequest)
                    <form method="POST" action="{{ route('it-support-requests.comments.store', $supportRequest) }}" enctype="multipart/form-data" class="space-y-4 border-t border-gray-200 pt-5">
                        @csrf
                        <div>
                            <label for="comment-body" class="block text-sm font-medium text-gray-700">Add a comment or work note</label>
                            <textarea id="comment-body" name="body" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">{{ old('body') }}</textarea>
                            @error('body')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @can('internalNote', $supportRequest)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_internal" value="1" @checked(old('is_internal')) class="rounded border-gray-300 text-blue-700">
                                Internal note (hidden from requester)
                            </label>
                        @endcan

                        @can('addProof', $supportRequest)
                            <div>
                                <label for="proof" class="block text-sm font-medium text-gray-700">Attach proof (optional)</label>
                                <p class="mt-1 text-xs text-gray-500">Image or PDF, up to 10 MB.</p>
                                <input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" class="mt-2 block w-full text-sm text-gray-700">
                                @error('proof')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endcan

                        <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                            Add to discussion
                        </button>
                    </form>
                @else
                    @if ($supportRequest->status->value === 'closed')
                        <p class="border-t border-gray-200 pt-4 text-sm text-gray-600">This request is complete and can no longer be updated.</p>
                    @endif
                @endcan
            </section>

            <div class="flex justify-between">
                <a href="{{ route('it-support-requests.index') }}" class="font-medium text-blue-700 hover:text-blue-900">
                    Back to my requests
                </a>
                <a href="{{ route('it-support-requests.create') }}" class="font-medium text-blue-700 hover:text-blue-900">
                    Submit another request
                </a>
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
