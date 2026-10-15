<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <label for="assigned-requests-status" class="text-sm font-medium text-gray-700">Filter by status</label>
        <select id="assigned-requests-status" wire:model.live="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All statuses</option>
            @foreach (\App\Enums\ItSupportRequestStatus::cases() as $statusOption)
                <option value="{{ $statusOption->value }}">{{ \Illuminate\Support\Str::headline($statusOption->value) }}</option>
            @endforeach
        </select>
    </div>

    @if ($requests->isEmpty())
        <div class="rounded-lg bg-white p-8 text-center text-sm text-gray-700 shadow-sm sm:rounded-lg">
            No requests are assigned to you right now.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Reference</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Requester</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Request</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Priority</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Age</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Assigned</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requests as $supportRequest)
                        @php
                            $shortTitle = $supportRequest->summary ?: $supportRequest->support_type;
                            $ageDate = $supportRequest->requested_at ?? $supportRequest->created_at;
                            $ageDays = (int) $ageDate->diffInDays(now());
                            $priorityClass = match ($supportRequest->priority) {
                                \App\Enums\ItSupportRequestPriority::Urgent => 'bg-red-100 text-red-800',
                                \App\Enums\ItSupportRequestPriority::High => 'bg-amber-100 text-amber-800',
                                \App\Enums\ItSupportRequestPriority::Normal => 'bg-blue-100 text-blue-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                            $statusClass = match ($supportRequest->status) {
                                \App\Enums\ItSupportRequestStatus::Assigned => 'bg-indigo-100 text-indigo-800',
                                \App\Enums\ItSupportRequestStatus::InProgress => 'bg-blue-100 text-blue-800',
                                \App\Enums\ItSupportRequestStatus::OnHold => 'bg-amber-100 text-amber-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                            $requestUrl = route('it-support-requests.show', $supportRequest);
                        @endphp
                        <tr tabindex="0" class="cursor-pointer hover:bg-blue-50 focus-within:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                            x-on:click="if (!$event.target.closest('a,button')) window.location.href = '{{ $requestUrl }}'"
                            x-on:keydown.enter="if (!$event.target.closest('a,button')) window.location.href = '{{ $requestUrl }}'">
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold">
                                <a href="{{ $requestUrl }}" wire:navigate class="text-blue-700 hover:text-blue-900">{{ $supportRequest->ticket_no }}</a>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-700">
                                <span class="font-medium">{{ $supportRequest->requester_name }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ $supportRequest->division }}</span>
                            </td>
                            <td class="max-w-xs px-5 py-4 text-sm text-gray-700">
                                <span class="block truncate font-medium" title="{{ $shortTitle }}">{{ $shortTitle }}</span>
                                <span class="mt-1 block truncate text-xs text-gray-500" title="{{ $supportRequest->support_type }}">{{ $supportRequest->support_type }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClass }}">{{ \Illuminate\Support\Str::headline($supportRequest->priority->value) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                {{ $ageDays < 1 ? 'Today' : $ageDays.' '.\Illuminate\Support\Str::plural('day', $ageDays) }}
                                @if ($supportRequest->is_overdue)
                                    <span class="mt-1 block text-xs font-semibold text-red-700">Overdue</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">{{ $supportRequest->assigned_at?->format('M j, Y g:i A') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-1 py-2">{{ $requests->links() }}</div>
    @endif
</div>
