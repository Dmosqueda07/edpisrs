<x-app-layout>
    <x-slot name="header">
        <p class="page-kicker">EDP IT SUPPORT</p>
        <h2 class="page-title">{{ __('Your support home') }}</h2>
    </x-slot>

    <div class="app-content-width dashboard-content">
        <section class="dashboard-welcome" aria-labelledby="dashboard-title">
            <div class="dashboard-welcome-copy">
                <p class="eyebrow eyebrow-blue">YOUR SUPPORT PORTAL</p>
                <h1 id="dashboard-title">What can we<br><span>help with today?</span></h1>
                <p>Submit a support request or pick up where you left off. Your requests and their details are available whenever you need them.</p>
            </div>
            <div class="dashboard-actions" aria-label="Support actions">
                <a class="dashboard-action dashboard-action-primary" href="{{ route('it-support-requests.create') }}" wire:navigate>
                    <span class="dashboard-action-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </span>
                    <span class="dashboard-action-copy"><strong>Submit a request</strong><span>Tell us what you need help with.</span></span>
                    <span class="dashboard-action-arrow" aria-hidden="true">↗</span>
                </a>
                <a class="dashboard-action dashboard-action-secondary" href="{{ route('it-support-requests.index') }}" wire:navigate>
                    <span class="dashboard-action-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5h16M4 12h16M4 16.5h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="18" cy="16.5" r="2.5" stroke="currentColor" stroke-width="1.6"/></svg>
                    </span>
                    <span class="dashboard-action-copy"><strong>Track my requests</strong><span>Review your existing support requests.</span></span>
                    <span class="dashboard-action-arrow" aria-hidden="true">↗</span>
                </a>
            </div>
        </section>
    </div>
</x-app-layout>
