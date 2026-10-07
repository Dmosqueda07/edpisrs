<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
    @if ($requests->isEmpty())
        <div class="p-8 text-center text-gray-700">No requests are currently assigned to you.</div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Requester</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Request</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assigned</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requests as $supportRequest)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium">
                                <a href="{{ route('it-support-requests.show', $supportRequest) }}" class="text-blue-700 hover:text-blue-900">
                                    #{{ $supportRequest->getKey() }}
                                </a>
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
                                {{ $supportRequest->assigned_at?->format('M j, Y g:i A') }}
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
