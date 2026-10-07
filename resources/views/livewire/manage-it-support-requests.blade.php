<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
    @if ($requests->isEmpty())
        <div class="p-8 text-center text-gray-700">There are no IT support requests to assign.</div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Requester</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Request</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assigned to</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assign technician</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requests as $supportRequest)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium">
                                <a href="{{ route('it-support-requests.show', $supportRequest) }}" class="text-blue-700 hover:text-blue-900">
                                    #{{ $supportRequest->getKey() }}
                                </a>
                                <div class="mt-1 text-xs text-gray-500">{{ $supportRequest->created_at->format('M j, Y g:i A') }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $supportRequest->requester_name }}
                                <div class="mt-1 text-xs text-gray-500">{{ $supportRequest->division }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $supportRequest->support_type }}
                                <div class="mt-1 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($supportRequest->details, 100) }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                {{ $supportRequest->technician?->full_name ?? 'Unassigned' }}
                            </td>
                            <td class="min-w-72 px-6 py-4">
                                @can('assign', $supportRequest)
                                    @if ($technicians->isNotEmpty())
                                    <form method="POST" action="{{ route('edp.it-support-requests.assign', $supportRequest) }}" class="flex items-start gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="technician-{{ $supportRequest->getKey() }}">Technician for request #{{ $supportRequest->getKey() }}</label>
                                        <select id="technician-{{ $supportRequest->getKey() }}" name="technician_id" required class="min-w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                            <option value="">Select technician</option>
                                            @foreach ($technicians as $technician)
                                                <option value="{{ $technician->user_id }}" @selected($supportRequest->assigned_technician_id === $technician->user_id)>
                                                    {{ $technician->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-md bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800">
                                            Assign
                                        </button>
                                    </form>
                                    @error('technician_id')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
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
                                            This request cannot be assigned in its current status.
                                        @endif
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 px-6 py-4">
            {{ $requests->links() }}
        </div>
    @endif
</div>
