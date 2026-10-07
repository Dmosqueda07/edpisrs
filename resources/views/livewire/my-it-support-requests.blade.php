<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
    @if ($requests->isEmpty())
        <div class="p-8 text-center">
            <p class="text-gray-700">You have not submitted any IT support requests yet.</p>
            <a href="{{ route('it-support-requests.create') }}" class="mt-3 inline-block font-medium text-blue-700 hover:text-blue-900">
                Submit your first request
            </a>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Details</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Submitted</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">View request</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($requests as $supportRequest)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">#{{ $supportRequest->getKey() }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $supportRequest->support_type }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ \Illuminate\Support\Str::limit($supportRequest->details, 100) }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ \Illuminate\Support\Str::headline($supportRequest->status->value) }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $supportRequest->created_at->format('M j, Y g:i A') }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <a href="{{ route('it-support-requests.show', $supportRequest) }}" class="font-medium text-blue-700 hover:text-blue-900">
                                    View
                                </a>
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
