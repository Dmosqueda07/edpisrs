@php($linkClass = ($mobile ? 'app-mobile-link' : 'app-nav-link').' whitespace-nowrap')
@php($activeClass = $mobile ? 'app-mobile-link-active' : 'app-nav-link-active')
@php($isActive = fn (string $pattern): bool => request()->routeIs($pattern))
<a class="{{ $linkClass }} {{ $isActive('dashboard') ? $activeClass : '' }}" href="{{ route('dashboard') }}" @if($isActive('dashboard')) aria-current="page" @endif wire:navigate>Dashboard</a>
@if($administrator)
    <a class="{{ $linkClass }} {{ $isActive('edp.it-support-requests.*') ? $activeClass : '' }}" href="{{ route('edp.it-support-requests.index') }}" @if($isActive('edp.it-support-requests.*')) aria-current="page" @endif wire:navigate>EDP intake @if($badgeCounts['intake'] > 0)<span class="app-nav-badge">{{ $badgeCounts['intake'] > 99 ? '99+' : $badgeCounts['intake'] }}</span>@endif</a>
@endif
@if($showAssigned)
    <a class="{{ $linkClass }} {{ $isActive('edp.assigned-requests.*') ? $activeClass : '' }}" href="{{ route('edp.assigned-requests.index') }}" @if($isActive('edp.assigned-requests.*')) aria-current="page" @endif wire:navigate>Assigned to me @if($badgeCounts['assigned'] > 0)<span class="app-nav-badge">{{ $badgeCounts['assigned'] > 99 ? '99+' : $badgeCounts['assigned'] }}</span>@endif</a>
@endif
@if($administrator)
    <a class="{{ $linkClass }} {{ $isActive('reports.*') ? $activeClass : '' }}" href="{{ route('reports.index') }}" @if($isActive('reports.*')) aria-current="page" @endif wire:navigate>Reports</a>
    <div class="app-admin-menu" x-data="{ adminOpen: false }" @click.outside="adminOpen = false" @keydown.escape="adminOpen = false">
        <button type="button" class="{{ $linkClass }} app-admin-trigger {{ $isActive('users.*') || $isActive('activity-logs.*') ? $activeClass : '' }}" @click="adminOpen = ! adminOpen" :aria-expanded="adminOpen.toString()" aria-haspopup="menu" aria-controls="{{ $mobile ? 'mobile-admin-menu' : 'desktop-admin-menu' }}" @if($isActive('users.*') || $isActive('activity-logs.*')) aria-current="page" @endif><span>Admin</span><svg class="app-nav-caret" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></button>
        <div id="{{ $mobile ? 'mobile-admin-menu' : 'desktop-admin-menu' }}" x-cloak x-show="adminOpen" x-transition class="app-nav-dropdown" role="menu">
            @can('viewAny', \App\Models\User::class)<a role="menuitem" href="{{ route('users.index') }}" @if($isActive('users.*')) aria-current="page" @endif wire:navigate>User management</a>@endcan
            @can('viewActivityLogs')<a role="menuitem" href="{{ route('activity-logs.index') }}" @if($isActive('activity-logs.*')) aria-current="page" @endif wire:navigate>Activity logs</a>@endcan
        </div>
    </div>
@else
    @can('viewApprovalQueue', \App\Models\ItSupportRequest::class)
        <a class="{{ $linkClass }} {{ $isActive('approvals.*') ? $activeClass : '' }}" href="{{ route('approvals.it-support-requests.index') }}" @if($isActive('approvals.*')) aria-current="page" @endif wire:navigate>Approvals @if($badgeCounts['approvals'] > 0)<span class="app-nav-badge app-nav-badge-attention">{{ $badgeCounts['approvals'] > 99 ? '99+' : $badgeCounts['approvals'] }}</span>@endif</a>
    @endcan
@endif
