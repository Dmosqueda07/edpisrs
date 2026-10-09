<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="EDP IT Support helps employees submit and follow technology support requests.">
        <title>EDP IT Support</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="portal-public">
        <div class="public-page">
            <header class="public-header">
                <a class="brand-lockup" href="{{ url('/') }}" aria-label="EDP IT Support home">
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

                <nav class="public-nav" aria-label="Main navigation">
                    <a href="#how-it-works">How it works</a>
                    @auth
                        <a class="public-nav-action" href="{{ route('dashboard') }}" wire:navigate>Go to dashboard</a>
                    @else
                        @if (Route::has('login'))
                            <a class="public-nav-action" href="{{ route('login') }}" wire:navigate>Sign in</a>
                        @endif
                    @endauth
                </nav>
            </header>

            <main>
                <section class="public-hero" aria-labelledby="hero-title">
                    <div class="hero-copy">
                        <p class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> YOUR EDP SUPPORT PORTAL</p>
                        <h1 id="hero-title">IT support,<br><span>without the</span><br>runaround.</h1>
                        <p class="hero-description">A straightforward place to tell us what’s not working and keep track of the support you need.</p>
                        <div class="hero-actions">
                            @auth
                                <a class="button button-primary" href="{{ route('it-support-requests.create') }}" wire:navigate>
                                    <span>Submit a request</span>
                                    <span class="button-arrow" aria-hidden="true">↗</span>
                                </a>
                                <a class="button button-text" href="{{ route('it-support-requests.index') }}" wire:navigate>Track a request</a>
                            @else
                                @if (Route::has('login'))
                                    <a class="button button-primary" href="{{ route('login') }}" wire:navigate>
                                        <span>Sign in to get support</span>
                                        <span class="button-arrow" aria-hidden="true">↗</span>
                                    </a>
                                @endif
                                <a class="button button-text" href="#how-it-works">See how it works <span aria-hidden="true">↓</span></a>
                            @endauth
                        </div>
                        <div class="hero-assurance">
                            <span class="assurance-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" fill="none"><path d="m5 10 3.2 3.2L15.5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.4"/></svg>
                            </span>
                            <span>One place to submit and follow your requests</span>
                        </div>
                    </div>

                    <div class="hero-art" aria-hidden="true">
                        <div class="hero-art-grid"></div>
                        <div class="hero-orbit hero-orbit-outer"></div>
                        <div class="hero-orbit hero-orbit-inner"></div>
                        <div class="hero-orb">
                            <svg viewBox="0 0 104 104" fill="none">
                                <path d="M22 54v-8a30 30 0 0 1 60 0v8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                <path d="M22 51h-3a8 8 0 0 0-8 8v9a8 8 0 0 0 8 8h9V51h-6Zm60 0h3a8 8 0 0 1 8 8v9a8 8 0 0 1-8 8h-9V51h6Z" fill="currentColor"/>
                                <path d="M78 74c-2 11-11 18-24 18h-7" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                <circle cx="42" cy="91" r="4" fill="currentColor"/>
                            </svg>
                        </div>
                        <div class="hero-art-caption">
                            <span class="caption-kicker">HERE WHEN YOU NEED US</span>
                            <span class="caption-line"></span>
                            <span>EDP IT Support</span>
                        </div>
                        <span class="hero-coordinate coordinate-top">01 / SUPPORT</span>
                        <span class="hero-coordinate coordinate-bottom">EDP · SERVICE PORTAL</span>
                    </div>
                </section>

                <section class="how-section" id="how-it-works" aria-labelledby="how-title">
                    <div class="how-heading">
                        <p class="eyebrow eyebrow-light">A CLEARER WAY TO GET HELP</p>
                        <h2 id="how-title">Start here.<br><span>We’ll take it from there.</span></h2>
                    </div>
                    <ol class="steps-list">
                        <li>
                            <span class="step-number">01</span>
                            <div><h3>Tell us what you need</h3><p>Share the issue and the details that will help us understand it.</p></div>
                            <span class="step-mark" aria-hidden="true">↗</span>
                        </li>
                        <li>
                            <span class="step-number">02</span>
                            <div><h3>Keep the conversation together</h3><p>Your request keeps its details and support conversation in one place.</p></div>
                            <span class="step-mark" aria-hidden="true">↗</span>
                        </li>
                        <li>
                            <span class="step-number">03</span>
                            <div><h3>Check in when it suits you</h3><p>Sign in to view your requests and see their current details.</p></div>
                            <span class="step-mark" aria-hidden="true">↗</span>
                        </li>
                    </ol>
                </section>
            </main>

            <footer class="public-footer">
                <a class="footer-brand" href="{{ url('/') }}">EDP <span>IT Support</span></a>
                <p>Support for the tools that keep your work moving.</p>
                <span class="footer-note">EMPLOYEE SUPPORT PORTAL</span>
            </footer>
        </div>
    </body>
</html>
