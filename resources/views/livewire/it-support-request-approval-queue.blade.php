<div>
    @if ($requests->isEmpty())
        <div class="rounded-lg bg-white p-8 text-center text-sm text-gray-700 shadow-sm sm:rounded-lg">
            No sensitive requests are waiting for your approval right now.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Reference</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Requester</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Request and key answers</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Priority</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Submitted</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requests as $supportRequest)
                        @php
                            $keyAnswers = $supportRequest->detailsRows
                                ->map(fn ($detail) => trim($detail->field_label.': '.$detail->field_value))
                                ->filter()
                                ->implode(' | ');
                            $keyAnswers = $keyAnswers !== '' ? $keyAnswers : ($supportRequest->details ?: 'No additional details');
                            $waitingDays = (int) ($supportRequest->requested_at ?? $supportRequest->created_at)->diffInDays(now());
                            $priorityClass = match ($supportRequest->priority) {
                                \App\Enums\ItSupportRequestPriority::Urgent => 'bg-red-100 text-red-800',
                                \App\Enums\ItSupportRequestPriority::High => 'bg-amber-100 text-amber-800',
                                \App\Enums\ItSupportRequestPriority::Normal => 'bg-blue-100 text-blue-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                            $statusClass = match ($supportRequest->status) {
                                \App\Enums\ItSupportRequestStatus::Assigned => 'bg-indigo-100 text-indigo-800',
                                \App\Enums\ItSupportRequestStatus::InProgress => 'bg-blue-100 text-blue-800',
                                \App\Enums\ItSupportRequestStatus::OnHold, \App\Enums\ItSupportRequestStatus::PendingApproval => 'bg-amber-100 text-amber-800',
                                \App\Enums\ItSupportRequestStatus::Approved => 'bg-green-100 text-green-800',
                                \App\Enums\ItSupportRequestStatus::Rejected, \App\Enums\ItSupportRequestStatus::Cancelled => 'bg-red-100 text-red-800',
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
                            <td class="max-w-md px-5 py-4 text-sm text-gray-700">
                                <span class="block font-medium">{{ $supportRequest->summary ?: $supportRequest->support_type }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ $supportRequest->support_type }}</span>
                                <span class="mt-1 block overflow-hidden text-xs text-gray-600" title="{{ $keyAnswers }}" style="display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2;">{{ $keyAnswers }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClass }}">{{ \Illuminate\Support\Str::headline($supportRequest->priority->value) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                <span class="block">{{ $supportRequest->created_at->format('M j, Y g:i A') }}</span>
                                <span class="mt-1 block text-xs font-medium text-amber-800">Waiting {{ $waitingDays }} {{ \Illuminate\Support\Str::plural('day', $waitingDays) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <a href="{{ $requestUrl }}" wire:navigate class="inline-flex rounded-md bg-blue-700 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-blue-800">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-1 py-2">{{ $requests->links() }}</div>
    @endif
</div>
