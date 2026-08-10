<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'POS — RIGHTCLICK' }}</title>
    <link rel="icon" href="{{ asset('images/brand/favicon/favicon.svg') }}">
    @vite(['resources/css/pos-theme.css'])
    @livewireStyles
</head>
<body class="pos-root min-h-screen">
    {{ $slot }}
    @livewireScripts
</body>
</html>
