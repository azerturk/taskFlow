<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TaskFlow · @yield('title', 'Workspace')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @php
        $user = auth()->user();
        $identity = filled($user->name) ? $user->name : $user->email;
        $role = $globalRole;
    @endphp

    <div class="min-h-screen lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
        <x-workspace-navigation :user="$user" :identity="$identity" :role="$role" />
        <div class="min-w-0">
            <x-workspace-header :user="$user" :identity="$identity" :role="$role">@yield('page-title', 'Workspace')</x-workspace-header>
            <main class="px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
                @foreach (['success', 'error', 'warning', 'info'] as $flashType)
                    @if (session($flashType))<x-flash-message :type="$flashType" :message="session($flashType)" />@endif
                @endforeach
                @yield('content')
            </main>
        </div>
    </div>

    <x-modal />
</body>
</html>
