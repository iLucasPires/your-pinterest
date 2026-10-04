<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? config('app.name'))</title>
    <meta name="description" content="@yield('description', $description ?? '')">

    <meta name="robots" content="@yield('robots', 'index, follow')">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    <script>
        window.localStorage.setItem('flux.appearance', 'light');
    </script>
    @fluxAppearance
    @stack('head')
</head>

<body class="min-h-full antialiased bg-taupe-50">
    @yield('content')
    @stack('scripts')
    @livewireScripts
    @fluxScripts

    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>
</body>

</html>
