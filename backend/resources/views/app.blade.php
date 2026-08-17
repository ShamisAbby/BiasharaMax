<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{--
            Read by every hand-rolled `fetch()` POST in the app — see
            resources/js/lib/postJson.ts.

            Inertia's own router does not need this (it uses the
            XSRF-TOKEN cookie), which is exactly why its absence went
            unnoticed: three screens were already reading this tag and
            sending an empty header, so those requests were rejected with
            419 and the fetch simply resolved to a failed response nobody
            checked.
        --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#4f46e5">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'BiasharaMax') }}">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        {{-- Applied before React hydrates so there's no flash of the wrong theme. --}}
        <script>
            (function () {
                var stored = localStorage.getItem('biasharaos-theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var isDark = stored === 'dark' || (stored === null && prefersDark);
                document.documentElement.classList.toggle('dark', isDark);
            })();
        </script>

        <!-- Service Worker registration -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js').catch(function () {});
                });
            }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
