<div class="{{ $mobile ? 'app-mobile-user-menu' : 'app-nav-account' }}" x-data="{ userOpen: false }" @click.outside="userOpen = false" @keydown.escape.window="userOpen = false">
    <button type="button" class="{{ $mobile ? 'app-mobile-user-trigger' : 'app-user-trigger' }}" @click="userOpen = ! userOpen" :aria-expanded="userOpen.toString()" aria-haspopup="menu" aria-controls="{{ $menuId }}">
        <span class="app-user-avatar" aria-hidden="true">{{ collect(explode(' ', $user->full_name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span>
        <span class="app-user-identity"><strong>{{ $user->full_name }}</strong><small>{{ $user->role?->value }}@if($user->division) <span aria-hidden="true">&middot;</span> {{ $user->division->value }}@endif</small></span>
        <svg class="app-user-caret" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
    </button>
    <div id="{{ $menuId }}" x-cloak x-show="userOpen" x-transition class="app-nav-dropdown" role="menu">
        <a role="menuitem" href="{{ route('it-support-requests.index') }}" @if(request()->routeIs('it-support-requests.index')) aria-current="page" @endif wire:navigate>My requests</a>
        <a role="menuitem" href="{{ route('it-support-requests.create') }}" @if(request()->routeIs('it-support-requests.create')) aria-current="page" @endif wire:navigate>Submit a request</a>
        <a role="menuitem" href="{{ route('profile') }}" @if(request()->routeIs('profile')) aria-current="page" @endif wire:navigate>Profile</a>
        <button role="menuitem" type="button" wire:click="logout">Log out</button>
    </div>
</div>
