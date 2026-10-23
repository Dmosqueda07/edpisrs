<?php

use App\Livewire\Actions\Logout;
use App\Models\ItSupportRequest;
use App\Services\NavigationBadgeCounts;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php($badgeCounts = app(NavigationBadgeCounts::class)->for(auth()->user()))
@php($administrator = auth()->user()->can('manageAssignments', ItSupportRequest::class))
@php($showAssigned = auth()->user()->can('viewAssigned', ItSupportRequest::class) || auth()->user()->can('viewAssignedQueue', ItSupportRequest::class))

<nav x-data="{ open: false }" x-on:keydown.escape.window="open = false" class="app-nav">
    <div class="app-nav-inner">
        <div class="flex justify-between h-16">
            <div class="app-nav-primary">
                <div class="app-nav-brand">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="app-logo-mark" />
                        <span class="app-brand-copy"><strong>EDP</strong><span>IT Support</span></span>
                    </a>
                </div>

                <div class="app-nav-links">
                    @include('livewire.layout.navigation-links', ['mobile' => false])
                </div>
            </div>

            @include('livewire.layout.user-menu', ['mobile' => false, 'user' => auth()->user(), 'menuId' => 'desktop-user-menu'])

            <div class="app-nav-toggle">
                <button type="button" @click="open = ! open" class="app-menu-button" aria-label="Toggle navigation" aria-controls="mobile-navigation" :aria-expanded="open.toString()">
                    <svg aria-hidden="true" class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div id="mobile-navigation" x-cloak x-show="open" x-transition class="app-mobile-menu">
        <div class="app-mobile-links">
            @include('livewire.layout.navigation-links', ['mobile' => true])
        </div>
        @include('livewire.layout.user-menu', ['mobile' => true, 'user' => auth()->user(), 'menuId' => 'mobile-user-menu'])
    </div>
</nav>
