@php
    $statusLabels = [
        'submitted' => 'Submitted',
        'pending_approval' => 'Pending approval',
        'approved' => 'Approved',
        'assigned' => 'Assigned',
        'in_progress' => 'In progress',
        'on_hold' => 'On hold',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
    ];
    $currentStatusLabel = $statusLabels[$status] ?? 'All';
    $openStatuses = ['', 'submitted', 'approved', 'assigned', 'in_progress', 'on_hold'];
@endphp

<div class="space-y-5">
    <section aria-label="Request status tabs" class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <div role="tablist" class="flex min-w-max border-b border-gray-200 px-2 sm:px-4">
            <button type="button" role="tab" data-status-tab="all" data-status-count="{{ $allCount }}" aria-controls="request-queue-results" wire:click="$set('status', '')" aria-selected="{{ $status === '' ? 'true' : 'false' }}"
                class="inline-flex items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 {{ $status === '' ? 'border-blue-700 text-blue-800' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">
                All <span class="rounded-full px-2 py-0.5 text-xs {{ $allCount === 0 ? 'bg-gray-100 text-gray-400' : 'bg-gray-100 text-gray-700' }}">{{ $allCount }}</span>
            </button>
            @foreach ($statuses as $statusOption)
                <button type="button" role="tab" data-status-tab="{{ $statusOption->value }}" data-status-count="{{ $statusCounts->get($statusOption->value, 0) }}" aria-controls="request-queue-results" wire:click="$set('status', '{{ $statusOption->value }}')" aria-selected="{{ $status === $statusOption->value ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 {{ $status === $statusOption->value ? 'border-blue-700 text-blue-800' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">
                    {{ $statusLabels[$statusOption->value] }}
                    <span class="rounded-full px-2 py-0.5 text-xs {{ ($statusCounts->get($statusOption->value, 0)) === 0 ? 'bg-gray-100 text-gray-400' : 'bg-gray-100 text-gray-700' }}">{{ $statusCounts->get($statusOption->value, 0) }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" aria-label="Request search and filters">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="queue-search" class="block text-sm font-medium text-gray-700">Requester or ticket</label>
                <input id="queue-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search by ticket number or requester"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="toggleFilters" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}" aria-controls="queue-filter-panel"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Filters
                    @if ($activeFilterCount > 0)
                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">{{ $activeFilterCount }}</span>
                    @endif
                </button>
                <label for="queue-sort" class="sr-only">Sort requests</label>
                <select id="queue-sort" wire:model.live="sort" class="rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    <option value="oldest">Oldest first</option>
                    <option value="newest">Newest first</option>
                    <option value="priority">Priority</option>
                </select>
            </div>
        </div>

        @if ($filtersOpen)
            <div id="queue-filter-panel" class="mt-4 border-t border-gray-200 pt-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="queue-type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select id="queue-type" wire:model.live="requestType" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">All types</option>
                            @foreach ($requestTypes as $requestTypeOption)
                                <option value="{{ $requestTypeOption->getKey() }}">{{ $requestTypeOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="queue-division" class="block text-sm font-medium text-gray-700">Division</label>
                        <select id="queue-division" wire:model.live="division" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">All divisions</option>
                            @foreach ($divisions as $divisionOption)
                                <option value="{{ $divisionOption->value }}">{{ $divisionOption->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="queue-priority" class="block text-sm font-medium text-gray-700">Priority</label>
                        <select id="queue-priority" wire:model.live="priority" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">All priorities</option>
                            @foreach ($priorities as $priorityOption)
                                <option value="{{ $priorityOption->value }}">{{ \Illuminate\Support\Str::headline($priorityOption->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="queue-technician" class="block text-sm font-medium text-gray-700">Technician</label>
                        <select id="queue-technician" wire:model.live="technician" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">All technicians</option>
                            <option value="unassigned">Unassigned</option>
                            @foreach ($technicians as $technicianOption)
                                <option value="{{ $technicianOption->getKey() }}">{{ $technicianOption->full_name }} ({{ $technicianOption->open_workload }} open)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="requested-from" class="block text-sm font-medium text-gray-700">Requested from</label>
                        <input id="requested-from" type="date" wire:model.live="requestedFrom" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="requested-to" class="block text-sm font-medium text-gray-700">Requested to</label>
                        <input id="requested-to" type="date" wire:model.live="requestedTo" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    </div>
                    @if (in_array($status, $openStatuses, true))
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 pb-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model.live="overdue" class="rounded border-gray-300 text-blue-700 focus:ring-blue-600">
                                Overdue only
                            </label>
                        </div>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
                    <p class="text-xs text-gray-500">Overdue means no activity for two working days.</p>
                    <div class="flex items-center gap-3">
                        @if ($activeFilterCount > 0 || $search !== '')
                            <button type="button" wire:click="clearFilters" class="text-sm font-medium text-blue-700 hover:text-blue-900">Clear filters</button>
                        @endif
                        <button type="button" wire:click="toggleFilters" class="text-sm font-medium text-gray-600 hover:text-gray-900">Done</button>
                    </div>
                </div>
            </div>
        @elseif ($activeFilterCount > 0 || $search !== '')
            <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
                <p class="text-xs text-gray-500">{{ $activeFilterCount }} active {{ \Illuminate\Support\Str::plural('filter', $activeFilterCount) }}</p>
                <button type="button" wire:click="clearFilters" class="text-sm font-medium text-blue-700 hover:text-blue-900">Clear filters</button>
            </div>
        @endif
    </section>

    <section id="request-queue-results" role="tabpanel" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="Request results">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Ticket</th>
                        <th scope="col" class="min-w-64 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Request</th>
                        <th scope="col" class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Priority</th>
                        <th scope="col" class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Technician</th>
                        <th scope="col" class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Age</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($requests as $supportRequest)
                            @php
                                $requestIsOverdue = in_array($supportRequest->status, [
                                    \App\Enums\ItSupportRequestStatus::Submitted,
                                    \App\Enums\ItSupportRequestStatus::Approved,
                                    \App\Enums\ItSupportRequestStatus::Assigned,
                                    \App\Enums\ItSupportRequestStatus::InProgress,
                                    \App\Enums\ItSupportRequestStatus::OnHold,
                                ], true) && \Carbon\Carbon::parse($supportRequest->last_activity_at)->lt($overdueCutoff);
                                $requestTitle = filled($supportRequest->summary)
                                    ? $supportRequest->summary
                                    : (filled($supportRequest->details)
                                        ? \Illuminate\Support\Str::limit($supportRequest->details, 72)
                                        : $supportRequest->support_type);
                                $requestTypeName = $supportRequest->requestType?->name ?? $supportRequest->support_type;
                            @endphp
                            <tr tabindex="0" data-request-modal-url="{{ route('it-support-requests.show', $supportRequest) }}" wire:key="support-request-{{ $supportRequest->getKey() }}"
                                class="cursor-pointer transition-colors hover:bg-blue-50 focus-within:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900">{{ $supportRequest->ticket_no }}</td>
                                <td class="px-5 py-4 text-sm">
                                    <span class="block font-medium text-gray-900">{{ $requestTitle }}</span>
                                    <span class="mt-1 block text-xs text-gray-500">
                                        {{ $requestTypeName }}
                                        <span aria-hidden="true">·</span> {{ $supportRequest->requester_name }}
                                        <span aria-hidden="true">·</span> {{ $supportRequest->division }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ match ($supportRequest->priority) {
                                            \App\Enums\ItSupportRequestPriority::Urgent => 'bg-red-100 text-red-800',
                                            \App\Enums\ItSupportRequestPriority::High => 'bg-orange-100 text-orange-800',
                                            \App\Enums\ItSupportRequestPriority::Normal => 'bg-blue-100 text-blue-800',
                                            default => 'bg-gray-100 text-gray-700',
                                        } }}">
                                        {{ \Illuminate\Support\Str::headline($supportRequest->priority->value) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">{{ $supportRequest->technician?->full_name ?? 'Unassigned' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $supportRequest->requested_at->diffForHumans() }}
                                    @if ($requestIsOverdue)
                                        <span class="mt-1 block text-xs font-semibold text-red-700">Overdue</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @can('assign', $supportRequest)
                                        @if ($status === \App\Enums\ItSupportRequestStatus::Submitted->value && $supportRequest->status === \App\Enums\ItSupportRequestStatus::Submitted)
                                            @if ($technicians->isEmpty())
                                                <span class="text-xs text-gray-500">No active EDP technicians are available.</span>
                                            @else
                                                <button type="button" data-queue-action="assign" wire:click="openAssignment({{ $supportRequest->getKey() }})" class="rounded-md border border-blue-200 px-3 py-1.5 text-sm font-medium text-blue-800 hover:bg-blue-50">
                                                    Assign
                                                </button>
                                            @endif
                                        @elseif ($status === \App\Enums\ItSupportRequestStatus::Approved->value && $supportRequest->status === \App\Enums\ItSupportRequestStatus::Approved)
                                            @if ($technicians->isEmpty())
                                                <span class="text-xs text-gray-500">No active EDP technicians are available.</span>
                                            @else
                                                <button type="button" data-queue-action="assign" wire:click="openAssignment({{ $supportRequest->getKey() }})" class="rounded-md border border-blue-200 px-3 py-1.5 text-sm font-medium text-blue-800 hover:bg-blue-50">
                                                    Assign
                                                </button>
                                            @endif
                                        @elseif (in_array($status, [\App\Enums\ItSupportRequestStatus::Assigned->value, \App\Enums\ItSupportRequestStatus::InProgress->value, \App\Enums\ItSupportRequestStatus::OnHold->value], true) && $supportRequest->status->value === $status)
                                            @if ($technicians->isEmpty())
                                                <span class="text-xs text-gray-500">No active EDP technicians are available.</span>
                                            @else
                                                <button type="button" data-queue-action="reassign" wire:click="openAssignment({{ $supportRequest->getKey() }})" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                    Reassign
                                                </button>
                                            @endif
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                            @if ($assignmentRequestId === $supportRequest->getKey()
                                && $assignmentRequest
                                && $status === $supportRequest->status->value
                                && in_array($status, [
                                    \App\Enums\ItSupportRequestStatus::Submitted->value,
                                    \App\Enums\ItSupportRequestStatus::Approved->value,
                                    \App\Enums\ItSupportRequestStatus::Assigned->value,
                                    \App\Enums\ItSupportRequestStatus::InProgress->value,
                                    \App\Enums\ItSupportRequestStatus::OnHold->value,
                                ], true))
                                <tr wire:key="assignment-panel-{{ $supportRequest->getKey() }}">
                                    <td colspan="6" class="bg-blue-50/60 px-5 py-4">
                                        <form method="POST" action="{{ route('edp.it-support-requests.assign', $supportRequest) }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
                                            @csrf
                                            @method('PATCH')
                                            <div>
                                                <label for="assignment-technician-{{ $supportRequest->getKey() }}" class="block text-xs font-semibold text-gray-700">Technician</label>
                                                <select id="assignment-technician-{{ $supportRequest->getKey() }}" name="technician_id" wire:model="assignmentTechnician" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                                    <option value="">Select technician</option>
                                                    @foreach ($technicians as $technicianOption)
                                                        <option value="{{ $technicianOption->getKey() }}" @selected($assignmentTechnician === (string) $technicianOption->getKey())>{{ $technicianOption->full_name }} · {{ $technicianOption->open_workload }} open</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label for="assignment-priority-{{ $supportRequest->getKey() }}" class="block text-xs font-semibold text-gray-700">Priority</label>
                                                <select id="assignment-priority-{{ $supportRequest->getKey() }}" name="priority" wire:model="assignmentPriority" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                                    @foreach ($priorities as $priorityOption)
                                                        <option value="{{ $priorityOption->value }}" @selected($assignmentPriority === $priorityOption->value)>{{ \Illuminate\Support\Str::headline($priorityOption->value) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500">Save</button>
                                            <button type="button" wire:click="closeAssignment" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                            @error('technician_id')<p class="text-xs text-red-700 sm:col-span-4">{{ $message }}</p>@enderror
                                            @error('priority')<p class="text-xs text-red-700 sm:col-span-4">{{ $message }}</p>@enderror
                                        </form>
                                    </td>
                                </tr>
                            @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center">
                                <p class="text-base font-medium text-gray-900">{{ $status === '' ? 'No requests to show.' : 'No requests in '.$currentStatusLabel.'.' }}</p>
                                <p class="mt-1 text-sm text-gray-500">Try another status or adjust your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-5 py-4">{{ $requests->links() }}</div>
    </section>

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
