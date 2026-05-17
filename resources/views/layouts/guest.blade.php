<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ brand() }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/">
                    <x-application-logo />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
        <script>
            // Refresh CSRF token when page is served from bfcache or PWA standalone launch
            function refreshCsrfToken() {
                fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        var m = html.match(/name="csrf-token"\s+content="([^"]+)"/);
                        if (!m) return;
                        var fresh = m[1];
                        document.querySelector('meta[name="csrf-token"]').content = fresh;
                        document.querySelectorAll('input[name="_token"]').forEach(function (el) { el.value = fresh; });
                    })
                    .catch(function () {});
            }
            window.addEventListener('pageshow', function (e) { if (e.persisted) refreshCsrfToken(); });
            if (window.matchMedia('(display-mode: standalone)').matches) refreshCsrfToken();
        </script>
    </body>
</html>
