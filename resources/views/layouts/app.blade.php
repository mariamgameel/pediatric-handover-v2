<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Pediatric Handover System') }}</title>
</head>
<body>
    <div id="flash-messages">
        @if (session('success'))
            <div class="flash-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="flash-error">{{ session('error') }}</div>
        @endif
        @if (session('warning'))
            <div class="flash-warning">{{ session('warning') }}</div>
        @endif
        @if (session('info'))
            <div class="flash-info">{{ session('info') }}</div>
        @endif
        @if (session('status'))
            <div class="flash-status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash-errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <main>
        @yield('content')
    </main>
</body>
</html>
