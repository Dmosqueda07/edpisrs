<x-app-layout>
    <x-slot name="header">
        <p class="page-kicker">EDP IT SUPPORT</p>
        <h2 class="page-title">{{ $dashboardType === 'administrator' ? __('Operations dashboard') : __('Your support home') }}</h2>
    </x-slot>

    <div class="app-content-width dashboard-content space-y-8">
        @if ($dashboardType === 'administrator')
            <section aria-labelledby="dashboard-counts">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-blue-700">Operations</p>
                        <h1 id="dashboard-counts" class="text-xl font-semibold text-gray-900">Request overview</h1>
                    </div>
                    <p class="text-sm text-gray-500">Current queue and today’s activity</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($cards as $card)
                        <a href="{{ $card['url'] }}" wire:navigate class="group rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm transition hover:border-blue-300 hover:shadow focus:outline-none focus:ring-2 focus:ring-blue-500" data-dashboard-card="{{ \Illuminate\Support\Str::slug($card['label']) }}">
                            <span class="flex items-center justify-between gap-2 text-sm font-medium text-gray-600 group-hover:text-blue-800">
                                {{ $card['label'] }}
                                <span aria-hidden="true" class="text-blue-600 transition group-hover:translate-x-0.5">↗</span>
                            </span>
                            <span class="mt-1 block text-2xl font-semibold tracking-tight text-[#122643]">{{ $card['count'] }}</span>
                            @if ($card['count'] === 0)<span class="text-xs text-gray-500">No matching requests</span>@endif
                        </a>
                    @endforeach
                </div>
            </section>

            @if ($assignedCount > 0)
                <p class="-mt-3 rounded-md border-l-2 border-blue-600 bg-blue-50 px-3 py-2 text-sm text-[#122643]">
                    You have {{ $assignedCount }} assigned {{ \Illuminate\Support\Str::plural('request', $assignedCount) }}.
                    <a href="{{ route('edp.assigned-requests.index') }}" wire:navigate class="ml-1 font-semibold text-blue-800 underline decoration-blue-300 underline-offset-2 hover:text-blue-950">View assigned to me</a>
                </p>
            @endif

            <section aria-labelledby="dashboard-shortcuts">
                <div class="mb-4 flex items-end justify-between gap-3">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-blue-700">Quick access</p>
                        <h2 id="dashboard-shortcuts" class="text-xl font-semibold text-gray-900">Operational shortcuts</h2>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article class="flex min-h-40 flex-col rounded-lg border border-gray-200 border-t-2 border-t-blue-700 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-[#122643]">User management</h3>
                        <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $shortcuts['users']['total'] }} <span class="text-sm font-normal text-gray-500">total users</span></p>
                        <div class="mt-2 flex flex-wrap gap-2 text-sm">
                            @if ($shortcuts['users']['pending'] > 0)
                                <span class="rounded-md bg-amber-100 px-2 py-1 font-medium text-amber-900">{{ $shortcuts['users']['pending'] }} waiting for approval</span>
                            @else
                                <span class="text-gray-500">No accounts waiting for approval</span>
                            @endif
                            @if ($shortcuts['users']['suspended'] > 0)<span class="text-gray-600">{{ $shortcuts['users']['suspended'] }} suspended</span>@else<span class="text-gray-500">No suspended accounts</span>@endif
                        </div>
                        <a href="{{ $shortcuts['users']['url'] }}" wire:navigate class="mt-auto pt-4 text-sm font-semibold text-blue-800 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500">Open user management <span aria-hidden="true">→</span></a>
                    </article>

                    <article class="flex min-h-40 flex-col rounded-lg border border-gray-200 border-t-2 border-t-blue-700 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-[#122643]">EDP intake</h3>
                        <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600">
                            <p><strong class="text-xl text-gray-900">{{ $shortcuts['intake']['awaiting'] }}</strong><br>Awaiting assignment</p>
                            <p><strong class="text-xl text-gray-900">{{ $shortcuts['intake']['overdue'] }}</strong><br>Overdue</p>
                        </div>
                        @if ($shortcuts['intake']['awaiting'] === 0)<p class="mt-2 text-xs text-gray-500">No requests awaiting assignment</p>@endif
                        @if ($shortcuts['intake']['overdue'] > 0)<span class="mt-2 w-fit rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">{{ $shortcuts['intake']['overdue'] }} need attention: overdue</span>@else<p class="mt-2 text-xs text-gray-500">No overdue requests</p>@endif
                        <a href="{{ $shortcuts['intake']['url'] }}" wire:navigate class="mt-auto pt-4 text-sm font-semibold text-blue-800 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500">Open EDP intake <span aria-hidden="true">→</span></a>
                    </article>

                    <article class="flex min-h-40 flex-col rounded-lg border border-gray-200 border-t-2 border-t-blue-700 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-[#122643]">Approvals</h3>
                        <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $shortcuts['approvals']['count'] }}</p>
                        <p class="text-sm text-gray-600">Requests waiting for a decision</p>
                        @if ($shortcuts['approvals']['count'] > 0)
                            <span class="mt-2 w-fit rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">Oldest has waited {{ $shortcuts['approvals']['oldest'] }}</span>
                        @else
                            <p class="mt-2 text-xs text-gray-500">No approvals waiting</p>
                        @endif
                        <a href="{{ $shortcuts['approvals']['url'] }}" wire:navigate class="mt-auto pt-4 text-sm font-semibold text-blue-800 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500">Open approvals <span aria-hidden="true">→</span></a>
                    </article>

                    <article class="flex min-h-40 flex-col rounded-lg border border-gray-200 border-t-2 border-t-blue-700 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-[#122643]">Reports</h3>
                        <p class="mt-3 text-sm text-gray-600">This month</p>
                        <div class="mt-1 flex gap-5 text-sm"><p><strong class="text-xl text-gray-900">{{ $shortcuts['reports']['received'] }}</strong><br>Received</p><p><strong class="text-xl text-gray-900">{{ $shortcuts['reports']['resolved'] }}</strong><br>Resolved</p></div>
                        @if ($shortcuts['reports']['received'] === 0 && $shortcuts['reports']['resolved'] === 0)<p class="mt-2 text-xs text-gray-500">No requests this month</p>@endif
                        <a href="{{ $shortcuts['reports']['url'] }}" wire:navigate class="mt-auto pt-4 text-sm font-semibold text-blue-800 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500">Open reports <span aria-hidden="true">→</span></a>
                    </article>

                    <article class="flex min-h-40 flex-col rounded-lg border border-gray-200 border-t-2 border-t-blue-700 bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-2">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div><h3 class="font-semibold text-[#122643]">Activity logs</h3><p class="mt-1 text-sm text-gray-500">Five most recent request events</p></div>
                            <a href="{{ $shortcuts['activity']['url'] }}" wire:navigate class="text-sm font-semibold text-blue-800 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500">View all activity <span aria-hidden="true">→</span></a>
                        </div>
                        @if ($shortcuts['activity']['entries']->isEmpty())
                            <p class="mt-5 rounded-md bg-gray-50 px-3 py-4 text-sm text-gray-600">No request activity has been recorded yet.</p>
                        @else
                            <ul class="mt-3 divide-y divide-gray-100">
                                @foreach ($shortcuts['activity']['entries'] as $entry)
                                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2 text-sm">
                                        <p class="min-w-0 flex-1 text-gray-700"><span class="font-medium text-gray-900">{{ $entry->user?->full_name ?? 'Unknown user' }}</span> {{ \App\Http\Controllers\ActivityLogController::actionLabel($entry->action) }} <span class="font-semibold text-blue-800">{{ $entry->itSupportRequest?->ticket_no ?? 'Request unavailable' }}</span></p>
                                        <time class="shrink-0 text-xs text-gray-500" datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->format('M j, Y g:i A') }}</time>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                </div>
            </section>
        @else
            <section class="dashboard-welcome" aria-labelledby="dashboard-title">
                <div class="dashboard-welcome-copy">
                    <p class="eyebrow eyebrow-blue">YOUR SUPPORT PORTAL</p>
                    <h1 id="dashboard-title">What can we<br><span>help with today?</span></h1>
                    <p>Submit a support request or pick up where you left off. Your requests and their details are available whenever you need them.</p>
                </div>
                <div class="dashboard-actions" aria-label="Support actions">
                    <a class="dashboard-action dashboard-action-primary" href="{{ route('it-support-requests.create') }}" wire:navigate>
                        <span class="dashboard-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                        <span class="dashboard-action-copy"><strong>Submit a request</strong><span>Tell us what you need help with.</span></span><span class="dashboard-action-arrow" aria-hidden="true">↗</span>
                    </a>
                    <a class="dashboard-action dashboard-action-secondary" href="{{ route('it-support-requests.index') }}" wire:navigate>
                        <span class="dashboard-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5h16M4 12h16M4 16.5h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="18" cy="16.5" r="2.5" stroke="currentColor" stroke-width="1.6"/></svg></span>
                        <span class="dashboard-action-copy"><strong>Track my requests</strong><span>Review your existing support requests.</span></span><span class="dashboard-action-arrow" aria-hidden="true">↗</span>
                    </a>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
