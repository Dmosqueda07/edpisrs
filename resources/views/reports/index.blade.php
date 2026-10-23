<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Reporting</p>
            <h1 class="text-2xl font-bold text-slate-900">Reports</h1>
        </div>
    </x-slot>

    <section class="app-content-width py-6 sm:py-8">
        <form method="GET" action="{{ route('reports.index') }}" class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 lg:items-end">
                <label class="block text-sm font-semibold text-slate-700">Date range
                    <select name="preset" class="mt-1 block min-h-11 w-full rounded-md border-slate-300">
                        <option value="this_month" @selected($preset === 'this_month')>This month</option>
                        <option value="last_month" @selected($preset === 'last_month')>Last month</option>
                        <option value="this_quarter" @selected($preset === 'this_quarter')>This quarter</option>
                        <option value="custom" @selected($preset === 'custom')>Custom</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-700">From
                    <input type="date" name="start" value="{{ $start->toDateString() }}" class="mt-1 block min-h-11 w-full rounded-md border-slate-300">
                </label>
                <label class="block text-sm font-semibold text-slate-700">To
                    <input type="date" name="end" value="{{ $end->toDateString() }}" class="mt-1 block min-h-11 w-full rounded-md border-slate-300">
                </label>
                @if($tab === 'accomplishments')
                    <label class="block text-sm font-semibold text-slate-700">Technician
                        <select name="technician" class="mt-1 block min-h-11 w-full rounded-md border-slate-300">
                            <option value="">All technicians</option>
                            @foreach($technicianOptions as $technician)
                                <option value="{{ $technician->user_id }}" @selected((string) $technicianId === (string) $technician->user_id)>{{ $technician->full_name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button class="min-h-11 rounded-md bg-blue-700 px-5 font-semibold text-white hover:bg-blue-800">Apply filters</button>
            </div>
            @if($errors->any()) <p class="mt-3 text-sm text-red-700">Please check the date range and try again.</p> @endif
        </form>

        @php($tabs = ['accomplishments' => 'Accomplishment report', 'division' => 'Requests by division', 'type' => 'Requests by type', 'turnaround' => 'Turnaround', 'status' => 'Status summary'])
        <nav aria-label="Report types" class="mb-5 flex gap-2 overflow-x-auto border-b border-slate-200 pb-2">
            @foreach($tabs as $key => $label)
                <a href="{{ route('reports.index', ['preset' => $preset, 'start' => $start->toDateString(), 'end' => $end->toDateString(), 'technician' => $technicianId, 'tab' => $key]) }}" @class(['whitespace-nowrap rounded-t-md px-4 py-3 text-sm font-semibold', 'bg-blue-700 text-white' => $tab === $key, 'text-slate-600 hover:bg-blue-50 hover:text-blue-800' => $tab !== $key]) aria-current="{{ $tab === $key ? 'page' : 'false' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ $tabs[$tab] }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $start->format('M j, Y') }} – {{ $end->format('M j, Y') }}</p>
                </div>
            </div>

            @if($tab === 'accomplishments')
                @if($completed->isEmpty())
                    <div class="rounded-lg bg-slate-50 px-5 py-10 text-center text-slate-600">No completed requests were found for this period.</div>
                @else
                    <div class="overflow-x-auto"><table class="min-w-[900px] w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="p-3">Ticket number</th><th class="p-3">Date received</th><th class="p-3">Requester</th><th class="p-3">Division</th><th class="p-3">Request type</th><th class="p-3">Action taken</th><th class="p-3">Date completed</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @foreach($completed as $item)<tr class="align-top"><td class="whitespace-nowrap p-3 font-semibold text-blue-800">{{ $item->ticket_no }}</td><td class="whitespace-nowrap p-3">{{ $item->requested_at->format('M j, Y') }}</td><td class="p-3">{{ $item->requester?->full_name ?: $item->requester_name }}</td><td class="p-3">{{ $item->division }}</td><td class="p-3">{{ $item->requestType?->name ?? $item->support_type }}</td><td class="max-w-xs p-3">{{ $item->resolution_notes ?: 'No resolution notes recorded' }}</td><td class="whitespace-nowrap p-3">{{ $item->completed_at->format('M j, Y') }}</td></tr>@endforeach
                    </tbody></table></div>
                    <p class="mt-4 text-sm font-semibold text-slate-700">Total completed: {{ $completed->count() }}</p>
                @endif
            @elseif($tab === 'division')
                @if($divisionTotal === 0)<div class="rounded-lg bg-slate-50 px-5 py-10 text-center text-slate-600">No requests were received in this period.</div>@else
                    <div class="overflow-x-auto"><table class="w-full min-w-[420px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="p-3">Division</th><th class="p-3">Requests</th><th class="p-3">Share</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($divisionRows as $row)<tr><td class="p-3 font-medium">{{ $row->division }}</td><td class="p-3">{{ $row->total }}</td><td class="p-3">{{ number_format($row->total / $divisionTotal * 100, 1) }}%</td></tr>@endforeach</tbody><tfoot class="border-t font-bold"><tr><td class="p-3">Total</td><td class="p-3">{{ $divisionTotal }}</td><td class="p-3">100%</td></tr></tfoot></table></div>
                @endif
            @elseif($tab === 'type')
                @if($typeRows->isEmpty())<div class="rounded-lg bg-slate-50 px-5 py-10 text-center text-slate-600">No requests were received in this period.</div>@else
                    <div class="overflow-x-auto"><table class="w-full min-w-[420px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="p-3">Request type</th><th class="p-3">Requests</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($typeRows as $row)<tr><td class="p-3 font-medium">{{ $row->name ?? 'Unknown type' }}</td><td class="p-3">{{ $row->total }}</td></tr>@endforeach</tbody><tfoot class="border-t font-bold"><tr><td class="p-3">Total</td><td class="p-3">{{ $typeRows->sum('total') }}</td></tr></tfoot></table></div>
                @endif
            @elseif($tab === 'turnaround')
                @php($hasTurnaround = count($turnaround['types']) + count($turnaround['technicians']) > 0)
                @if(!$hasTurnaround)<div class="rounded-lg bg-slate-50 px-5 py-10 text-center text-slate-600">No requests were received in this period.</div>@else
                    @foreach(['types' => 'By request type', 'technicians' => 'By technician'] as $group => $heading)
                        <h3 class="mb-2 mt-5 font-semibold text-slate-800">{{ $heading }}</h3><div class="overflow-x-auto"><table class="w-full min-w-[550px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="p-3">{{ $group === 'types' ? 'Request type' : 'Technician' }}</th><th class="p-3">Submission to assignment</th><th class="p-3">Assignment to completion</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($turnaround[$group] as $name => $metrics)<tr><td class="p-3 font-medium">{{ $name }}</td><td class="p-3">{{ $metrics['assignment'] }}</td><td class="p-3">{{ $metrics['completion'] }}</td></tr>@endforeach</tbody></table></div>
                    @endforeach
                    <p class="mt-4 text-xs text-slate-500">Averages include requests with both timestamps for each interval. Requests are grouped by date received.</p>
                @endif
            @else
                @php($hasStatus = array_sum($statusCounts) > 0)
                @if(!$hasStatus)<div class="rounded-lg bg-slate-50 px-5 py-10 text-center text-slate-600">No requests were received in this period.</div>@else
                    @php($resolvedCount = $statusCounts['resolved'] + $statusCounts['closed'])
                    @php($openCount = array_sum($statusCounts) - $resolvedCount)
                    <div class="mb-4 grid gap-3 sm:grid-cols-2"><div class="rounded-lg bg-blue-50 p-4"><p class="text-sm text-slate-600">Open</p><p class="mt-1 text-3xl font-bold text-blue-800">{{ $openCount }}</p></div><div class="rounded-lg bg-emerald-50 p-4"><p class="text-sm text-slate-600">Resolved</p><p class="mt-1 text-3xl font-bold text-emerald-800">{{ $resolvedCount }}</p></div></div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[380px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="p-3">Status</th><th class="p-3">Requests</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($statusCounts as $status => $count)<tr><td class="p-3">{{ str($status)->replace('_', ' ')->title() }}</td><td class="p-3">{{ $count }}</td></tr>@endforeach</tbody><tfoot class="border-t font-bold"><tr><td class="p-3">Total</td><td class="p-3">{{ array_sum($statusCounts) }}</td></tr></tfoot></table></div>
                @endif
            @endif
        </section>
    </section>
</x-app-layout>
