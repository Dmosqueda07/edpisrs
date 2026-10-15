<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <label for="my-requests-status" class="text-sm font-medium text-gray-700">Filter by status</label>
        <select id="my-requests-status" wire:model.live="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All statuses</option>
            @foreach (\App\Enums\ItSupportRequestStatus::cases() as $statusOption)
                <option value="{{ $statusOption->value }}">{{ \Illuminate\Support\Str::headline($statusOption->value) }}</option>
            @endforeach
        </select>
    </div>

    @if ($requests->isEmpty())
        <div class="rounded-lg bg-white p-8 text-center shadow-sm sm:rounded-lg">
            <p class="text-gray-700">You have not submitted any IT support requests yet.</p>
            <a href="{{ route('it-support-requests.create') }}" class="mt-3 inline-block font-medium text-blue-700 hover:text-blue-900">
                Submit your first request
            </a>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Request</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Priority</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Submitted</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">View request</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requests as $supportRequest)
                        @php($requestUrl = route('it-support-requests.show', $supportRequest))
                        <tr tabindex="0" data-request-modal-url="{{ $requestUrl }}" class="cursor-pointer transition-colors hover:bg-blue-50 focus-within:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ $supportRequest->ticket_no }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <span class="block font-medium text-gray-900">{{ $supportRequest->summary ?: $supportRequest->support_type }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ $supportRequest->support_type }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}</td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ match ($supportRequest->priority) { \App\Enums\ItSupportRequestPriority::Urgent => 'bg-red-100 text-red-800', \App\Enums\ItSupportRequestPriority::High => 'bg-orange-100 text-orange-800', \App\Enums\ItSupportRequestPriority::Normal => 'bg-blue-100 text-blue-800', default => 'bg-gray-100 text-gray-700' } }}">
                                    {{ \Illuminate\Support\Str::headline($supportRequest->priority->value) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $supportRequest->created_at->format('M j, Y g:i A') }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <a href="{{ $requestUrl }}" data-request-modal-trigger wire:navigate class="font-medium text-blue-700 hover:text-blue-900">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-1 py-2">{{ $requests->links() }}</div>
    @endif

    <div id="request-detail-dialog" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-gray-950/60 p-4 opacity-0 transition-opacity duration-150" aria-hidden="true">
        <section class="flex h-[85vh] max-h-[85vh] w-[92vw] max-w-[640px] flex-col overflow-hidden rounded-xl bg-white opacity-0 shadow-2xl transition-opacity duration-150" role="dialog" aria-modal="true" aria-labelledby="request-modal-ticket">
            <header class="shrink-0 border-b border-gray-200 px-5 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 id="request-modal-ticket" class="truncate text-xl font-semibold text-gray-900">Request details</h2>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span id="request-modal-status" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"></span>
                            <span id="request-modal-priority" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"></span>
                        </div>
                    </div>
                    <button type="button" id="request-modal-close" class="shrink-0 rounded-md border border-gray-300 p-2 text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close request details">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    </button>
                </div>
            </header>
            <div id="request-modal-body" class="min-h-0 flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6" aria-live="polite">
                <p class="text-sm text-gray-600">Loading request…</p>
            </div>
            <footer class="shrink-0 border-t border-gray-200 bg-white p-4 sm:p-5">
                <div id="request-modal-actions" class="flex flex-wrap items-end gap-3"></div>
                <div id="request-modal-footer-links" class="mt-3 flex items-center justify-between gap-4">
                    <a id="request-modal-full-page" href="#" class="text-sm font-medium text-blue-700 hover:text-blue-900">Open full page</a>
                    <button type="button" id="request-modal-cancel-open" class="hidden rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">Cancel request</button>
                </div>
                <section id="request-modal-cancel-panel" class="mt-3 hidden rounded-lg border border-gray-200 bg-gray-50 p-4" aria-labelledby="request-modal-cancel-heading">
                    <h3 id="request-modal-cancel-heading" class="text-base font-semibold text-gray-900">Cancel this request?</h3>
                    <p class="mt-1 text-sm text-gray-600">This stops work on the request. You can't undo it.</p>
                    <div id="request-modal-cancel-form" class="mt-3"></div>
                    <div id="request-modal-cancel-buttons" class="mt-3 flex flex-wrap justify-end gap-2">
                        <button type="button" id="request-modal-cancel-keep" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">Keep request</button>
                    </div>
                </section>
            </footer>
        </section>
    </div>
    <template id="request-image-preview-template">
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-950/90 p-4" role="dialog" aria-modal="true" aria-label="Image preview">
            <button type="button" data-preview-close class="absolute right-4 top-4 z-10 rounded-md bg-white/10 p-3 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white" aria-label="Close image preview">×</button>
            <button type="button" data-preview-previous class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white" aria-label="Previous image">‹</button>
            <figure class="m-0 flex max-h-full max-w-full flex-col items-center justify-center gap-3">
                <img data-preview-image alt="" style="max-width:80vw;max-height:70vh;width:auto;height:auto;object-fit:contain;display:block">
                <figcaption class="max-w-[80vw] text-center text-sm text-white">
                    <span data-preview-name class="block break-words font-medium"></span>
                    <a data-preview-original target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-blue-200 underline hover:text-white">View original</a>
                </figcaption>
            </figure>
            <button type="button" data-preview-next class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white" aria-label="Next image">›</button>
        </div>
    </template>
</div>
