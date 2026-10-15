<div class="space-y-6">
    <form class="grid gap-4 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4" role="search">
        <div>
            <x-input-label for="queue-search" :value="__('Ticket or requester')" />
            <x-text-input id="queue-search" type="search" wire:model.live.debounce.300ms="search" class="mt-1 block w-full" placeholder="Ticket number or name" />
        </div>
        <div>
            <x-input-label for="queue-status" :value="__('Status')" />
            <select id="queue-status" wire:model.live="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">All statuses</option>
                @foreach ($statuses as $item)
                    <option value="{{ $item->value }}">{{ \Illuminate\Support\Str::headline($item->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="queue-type" :value="__('Request type')" />
            <select id="queue-type" wire:model.live="requestType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">All request types</option>
                @foreach ($requestTypes as $requestTypeOption)
                    <option value="{{ $requestTypeOption->getKey() }}">{{ $requestTypeOption->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="queue-division" :value="__('Division')" />
            <select id="queue-division" wire:model.live="division" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">All divisions</option>
                @foreach ($divisions as $divisionOption)
                    <option value="{{ $divisionOption->value }}">{{ $divisionOption->value }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="queue-priority" :value="__('Priority')" />
            <select id="queue-priority" wire:model.live="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">All priorities</option>
                @foreach ($priorities as $priorityOption)
                    <option value="{{ $priorityOption->value }}">{{ ucfirst($priorityOption->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="queue-technician" :value="__('Assigned technician')" />
            <select id="queue-technician" wire:model.live="technician" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">All technicians</option>
                <option value="unassigned">Unassigned</option>
                @foreach ($technicians as $technicianOption)
                    <option value="{{ $technicianOption->getKey() }}">
                        {{ $technicianOption->full_name }} ({{ $technicianOption->open_workload }} open)
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="requested-from" :value="__('Requested from')" />
            <x-text-input id="requested-from" type="date" wire:model.live="requestedFrom" class="mt-1 block w-full" />
        </div>
        <div>
            <x-input-label for="requested-to" :value="__('Requested to')" />
            <x-text-input id="requested-to" type="date" wire:model.live="requestedTo" class="mt-1 block w-full" />
        </div>
        <div>
            <x-input-label for="queue-sort" :value="__('Sort')" />
            <select id="queue-sort" wire:model.live="sort" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="age">Oldest first</option>
                <option value="priority">Priority, then age</option>
            </select>
        </div>
        <div class="flex flex-wrap items-end gap-x-4 gap-y-2 lg:col-span-3">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="awaitingAssignment" class="rounded border-gray-300 text-blue-700">
                Awaiting assignment
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="resolvedToday" class="rounded border-gray-300 text-blue-700">
                Resolved today
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="overdue" class="rounded border-gray-300 text-blue-700">
                Overdue only
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="recent" value="7d" class="rounded border-gray-300 text-blue-700">
                New in last 7 days
            </label>
            <a class="text-sm text-blue-700 underline" href="{{ route('edp.it-support-requests.index') }}" wire:navigate>Clear filters</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        @if ($requests->isEmpty())
            <div class="p-8 text-center text-gray-700">There are no IT support requests matching these filters.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ticket</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type / requester</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Priority / status</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assigned technician</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Age</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($requests as $supportRequest)
                            @php
                                $requestIsOverdue = in_array($supportRequest->status, [
                                    \App\Enums\ItSupportRequestStatus::Submitted,
                                    \App\Enums\ItSupportRequestStatus::Approved,
                                    \App\Enums\ItSupportRequestStatus::Assigned,
                                    \App\Enums\ItSupportRequestStatus::InProgress,
                                    \App\Enums\ItSupportRequestStatus::OnHold,
                                ], true) && \Carbon\Carbon::parse($supportRequest->last_activity_at)->lt($overdueCutoff);
                            @endphp
                            <tr wire:key="support-request-{{ $supportRequest->getKey() }}">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium">
                                    <a href="{{ route('it-support-requests.show', $supportRequest) }}" class="text-blue-700 hover:text-blue-900">
                                        {{ $supportRequest->ticket_no }}
                                    </a>
                                    <span class="block text-xs text-gray-500">#{{ $supportRequest->getKey() }}</span>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <div class="font-medium">{{ $supportRequest->summary ?: $supportRequest->support_type }}</div>
                                    <div class="text-xs text-gray-500">{{ $supportRequest->requestType?->name ?? $supportRequest->support_type }}</div>
                                    <div>{{ $supportRequest->requester_name }}</div>
                                    <div class="text-xs text-gray-500">{{ $supportRequest->division }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($supportRequest->details, 100) }}</div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                        {{ $supportRequest->priority->value === 'urgent' ? 'bg-red-100 text-red-800' : ($supportRequest->priority->value === 'high' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800') }}">
                                        {{ ucfirst($supportRequest->priority->value) }}
                                    </span>
                                    <div class="mt-1 text-gray-700">{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}</div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $supportRequest->technician?->full_name ?? 'Unassigned' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $supportRequest->requested_at->diffForHumans() }}
                                    @if ($requestIsOverdue)
                                        <span class="mt-1 block font-semibold text-red-700">Overdue</span>
                                    @endif
                                </td>
                                <td class="min-w-64 space-y-2 px-5 py-4">
                                    @can('assign', $supportRequest)
                                        @if ($technicians->isNotEmpty())
                                            <form method="POST" action="{{ route('edp.it-support-requests.assign', $supportRequest) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <label class="sr-only" for="technician-{{ $supportRequest->getKey() }}">Assign technician</label>
                                                <select id="technician-{{ $supportRequest->getKey() }}" name="technician_id" required class="min-w-40 rounded-md border-gray-300 text-xs shadow-sm">
                                                    <option value="">Select technician</option>
                                                    @foreach ($technicians as $technicianOption)
                                                        <option value="{{ $technicianOption->getKey() }}" @selected($supportRequest->assigned_technician_id === $technicianOption->getKey())>
                                                            {{ $technicianOption->full_name }} ({{ $technicianOption->open_workload }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="rounded-md bg-blue-700 px-3 py-2 text-xs font-semibold text-white">Assign</button>
                                            </form>
                                        @else
                                            <p class="text-xs text-gray-500">No active EDP technicians are available.</p>
                                        @endif
                                    @else
                                        <p class="text-xs text-gray-500">
                                            @if ($supportRequest->status === \App\Enums\ItSupportRequestStatus::PendingApproval)
                                                Awaiting approval before assignment.
                                            @elseif ($supportRequest->status === \App\Enums\ItSupportRequestStatus::Rejected)
                                                Rejected; assignment is unavailable.
                                            @else
                                                Assignment is unavailable in this status.
                                            @endif
                                        </p>
                                    @endcan

                                    <form method="POST" action="{{ route('edp.it-support-requests.priority', $supportRequest) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="priority-{{ $supportRequest->getKey() }}">Change priority</label>
                                        <select id="priority-{{ $supportRequest->getKey() }}" name="priority" class="rounded-md border-gray-300 text-xs shadow-sm">
                                            @foreach ($priorities as $priorityOption)
                                                <option value="{{ $priorityOption->value }}" @selected($supportRequest->priority === $priorityOption)>{{ ucfirst($priorityOption->value) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700">Save priority</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
