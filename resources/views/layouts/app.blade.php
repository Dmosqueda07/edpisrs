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
    <body class="font-sans antialiased portal-app">
        <div class="min-h-screen app-page">
            <livewire:layout.navigation />

            @if (isset($header))
                <header class="app-page-heading">
                    <div class="app-content-width">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <main class="app-main">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
