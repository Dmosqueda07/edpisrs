<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'EDP IT Support') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased guest-page">
        <div class="guest-shell">
            <header class="guest-header">
                <a class="brand-lockup" href="{{ url('/') }}" wire:navigate aria-label="EDP IT Support home">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none">
                            <path d="M7 17v-2a9 9 0 0 1 18 0v2" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M7 16H6a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3v-7H7Zm18 0h1a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-3v-7h2Z" fill="currentColor"/>
                            <path d="M23 23a7 7 0 0 1-7 5h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="brand-copy">
                        <strong>EDP</strong>
                        <span>IT Support</span>
                    </span>
                </a>
                <a class="guest-back-link" href="{{ url('/') }}" wire:navigate>
                    <span aria-hidden="true">←</span> Back to support
                </a>
            </header>

            <main class="guest-content">
                <div class="guest-intro">
                    <p class="eyebrow eyebrow-light">EDP EMPLOYEE PORTAL</p>
                    <h1>Your support,<br><span>all in one place.</span></h1>
                    <p>Sign in to submit a request, follow its details, and continue the conversation with the support team.</p>
                </div>
                <section class="guest-card" aria-label="Account access">
                    {{ $slot }}
                </section>
            </main>

            <footer class="guest-footer">
                <span>EDP IT SUPPORT</span>
                <span>Secure employee access</span>
            </footer>
        </div>
    </body>
</html>
