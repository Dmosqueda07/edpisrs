<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="app-nav">
    <!-- Primary Navigation Menu -->
    <div class="app-nav-inner">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="app-nav-brand">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="app-logo-mark" />
                        <span class="app-brand-copy"><strong>EDP</strong><span>IT Support</span></span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="app-nav-links">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('it-support-requests.create')" :active="request()->routeIs('it-support-requests.*')" wire:navigate>
                        {{ __('IT Support Request') }}
                    </x-nav-link>
                    <x-nav-link :href="route('it-support-requests.index')" :active="request()->routeIs('it-support-requests.index')" wire:navigate>
                        {{ __('My Requests') }}
                    </x-nav-link>
                    @can('manageAssignments', \App\Models\ItSupportRequest::class)
                        <x-nav-link :href="route('edp.it-support-requests.index')" :active="request()->routeIs('edp.it-support-requests.*')" wire:navigate>
                            {{ __('EDP Intake') }}
                        </x-nav-link>
                    @endcan
                    @can('viewApprovalQueue', \App\Models\ItSupportRequest::class)
                        <x-nav-link :href="route('approvals.it-support-requests.index')" :active="request()->routeIs('approvals.*')" wire:navigate>
                            {{ __('Approvals') }}
                        </x-nav-link>
                    @endcan
                    @can('viewAssigned', \App\Models\ItSupportRequest::class)
                        <x-nav-link :href="route('edp.assigned-requests.index')" :active="request()->routeIs('edp.assigned-requests.*')" wire:navigate>
                            {{ __('Assigned to Me') }}
                        </x-nav-link>
                    @endcan
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="app-nav-account">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="app-user-trigger">
                            <div x-data="{{ json_encode(['name' => auth()->user()->full_name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="app-nav-toggle">
                <button @click="open = ! open" class="app-menu-button" aria-label="Toggle navigation" :aria-expanded="open.toString()">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="app-mobile-menu">
        <div class="app-mobile-links">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('it-support-requests.create')" :active="request()->routeIs('it-support-requests.*')" wire:navigate>
                {{ __('IT Support Request') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('it-support-requests.index')" :active="request()->routeIs('it-support-requests.index')" wire:navigate>
                {{ __('My Requests') }}
            </x-responsive-nav-link>
            @can('manageAssignments', \App\Models\ItSupportRequest::class)
                <x-responsive-nav-link :href="route('edp.it-support-requests.index')" :active="request()->routeIs('edp.it-support-requests.*')" wire:navigate>
                    {{ __('EDP Intake') }}
                </x-responsive-nav-link>
            @endcan
            @can('viewApprovalQueue', \App\Models\ItSupportRequest::class)
                <x-responsive-nav-link :href="route('approvals.it-support-requests.index')" :active="request()->routeIs('approvals.*')" wire:navigate>
                    {{ __('Approvals') }}
                </x-responsive-nav-link>
            @endcan
            @can('viewAssigned', \App\Models\ItSupportRequest::class)
                <x-responsive-nav-link :href="route('edp.assigned-requests.index')" :active="request()->routeIs('edp.assigned-requests.*')" wire:navigate>
                    {{ __('Assigned to Me') }}
                </x-responsive-nav-link>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="app-mobile-account">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800" x-data="{{ json_encode(['name' => auth()->user()->full_name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
