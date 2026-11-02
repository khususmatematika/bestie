<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e3a8a">

    {{-- Terapkan tema sebelum render agar tidak berkedip --}}
    <script @if (! empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
        (function () {
            try {
                var t = localStorage.getItem('banksoal-theme') || 'light';
                var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (dark) document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="h-full">
    @inertia
</body>
</html>
