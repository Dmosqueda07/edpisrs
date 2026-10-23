<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Administration</p>
            <h1 class="text-2xl font-bold text-slate-900">Activity logs</h1>
        </div>
    </x-slot>

    <section class="app-content-width py-6 sm:py-8">
        @php($tabQuery = request()->query())
        <nav aria-label="Activity log types" class="mb-5 flex gap-2 border-b border-slate-200 pb-2">
            <a href="{{ route('activity-logs.index', array_merge($tabQuery, ['tab' => 'requests', 'page' => null])) }}" @class(['rounded-t-md px-4 py-3 text-sm font-semibold', 'bg-blue-700 text-white' => $tab === 'requests', 'text-slate-600 hover:bg-blue-50 hover:text-blue-800' => $tab !== 'requests']) aria-current="{{ $tab === 'requests' ? 'page' : 'false' }}">Requests</a>
            @can('viewAccountActivityLogs')
                <a href="{{ route('activity-logs.index', array_merge($tabQuery, ['tab' => 'accounts', 'page' => null])) }}" @class(['rounded-t-md px-4 py-3 text-sm font-semibold', 'bg-blue-700 text-white' => $tab === 'accounts', 'text-slate-600 hover:bg-blue-50 hover:text-blue-800' => $tab !== 'accounts']) aria-current="{{ $tab === 'accounts' ? 'page' : 'false' }}">Accounts</a>
            @endcan
        </nav>

        @if($tab === 'requests')
            <form method="GET" action="{{ route('activity-logs.index') }}" class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <input type="hidden" name="tab" value="requests">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    <label class="block text-sm font-semibold text-slate-700">Ticket or person
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Ticket number or name" class="mt-1 block min-h-11 w-full rounded-md border-slate-300">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">From
                        <input type="date" name="start" value="{{ $filters['start'] ?? '' }}" class="mt-1 block min-h-11 w-full rounded-md border-slate-300">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">To
                        <input type="date" name="end" value="{{ $filters['end'] ?? '' }}" class="mt-1 block min-h-11 w-full rounded-md border-slate-300">
                    </label>
                    <div class="flex gap-2">
                        <label class="block min-w-0 flex-1 text-sm font-semibold text-slate-700">Action type
                            <select name="action" class="mt-1 block min-h-11 w-full rounded-md border-slate-300">
                                <option value="">All actions</option>
                                @foreach($actions as $key => $label)<option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <button class="mt-auto min-h-11 rounded-md bg-blue-700 px-4 font-semibold text-white hover:bg-blue-800">Filter</button>
                    </div>
                </div>
                @if($errors->any())<p class="mt-3 text-sm text-red-700">Please check the filters and date range.</p>@endif
            </form>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                @if($logs->isEmpty())
                    <div class="px-5 py-12 text-center"><h2 class="font-semibold text-slate-800">No activity matches these filters</h2><p class="mt-1 text-sm text-slate-600">Try a different ticket, person, date range, or action type.</p></div>
                @else
                    <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="p-3">Date and time</th><th class="p-3">Who did it</th><th class="p-3">Activity</th><th class="p-3">Ticket</th><th class="p-3">Status change</th><th class="p-3">Remarks</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($logs as $log)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap p-3">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                                    <td class="p-3">{{ $log->user?->full_name ?? 'System' }}<span class="mt-1 block text-xs text-slate-500">{{ $log->user?->role?->value ?? 'System' }}</span></td>
                                    <td class="p-3 font-medium text-slate-800">{{ \App\Http\Controllers\ActivityLogController::actionLabel($log->action) }}</td>
                                    <td class="whitespace-nowrap p-3">@if($log->itSupportRequest)<a class="font-semibold text-blue-700 underline hover:text-blue-900" href="{{ route('it-support-requests.show', $log->itSupportRequest) }}">{{ $log->itSupportRequest->ticket_no }}</a>@else—@endif</td>
                                    <td class="whitespace-nowrap p-3">@if($log->from_status && $log->to_status){{ str($log->from_status)->replace('_', ' ')->title() }} → {{ str($log->to_status)->replace('_', ' ')->title() }}@else—@endif</td>
                                    <td class="max-w-sm whitespace-pre-wrap p-3 text-slate-600">{{ $log->remarks ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                    <div class="border-t border-slate-200 px-4 py-3">{{ $logs->links() }}</div>
                @endif
            </section>
        @else
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">Account activity</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">New account actions will appear here after the append-only audit table is approved and installed. Earlier actions remain in the daily audit log files; they will not be imported or parsed into this page.</p>
                <p class="mt-3 text-sm text-slate-600">Passwords and temporary passwords are never displayed or stored in the searchable account activity table.</p>
            </section>
        @endif
    </section>
</x-app-layout>
