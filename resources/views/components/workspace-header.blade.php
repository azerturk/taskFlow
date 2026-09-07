@props(['user', 'identity', 'role'])

<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex min-h-18 items-center gap-3 px-5 py-3 sm:px-8 lg:px-10">
        <button type="button" class="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-indigo-500/15 disabled:cursor-wait disabled:opacity-60 lg:hidden" aria-label="Open navigation" aria-controls="workspace-navigation" aria-expanded="false" data-mobile-nav-toggle disabled><span class="text-lg leading-none" aria-hidden="true">☰</span></button>
        <div class="min-w-0 flex-1"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">TaskFlow</p><h1 class="mt-0.5 truncate text-xl font-semibold tracking-tight text-slate-950">{{ $slot }}</h1></div>
        <div class="flex items-center gap-3">
            <a href="{{ route('notifications.index') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-indigo-500/15">Notifications @if($unreadNotificationCount)<span class="ml-1 rounded-full bg-indigo-600 px-1.5 py-0.5 text-xs text-white">{{ $unreadNotificationCount }}</span>@endif</a>
            <div class="hidden text-right sm:block"><p class="max-w-48 truncate text-sm font-semibold text-slate-900">{{ $identity }}</p><p class="text-xs text-slate-500">{{ $role ? ucwords(str_replace('_', ' ', $role)) : 'Workspace member' }}</p></div>
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700" aria-label="{{ $identity }}">{{ strtoupper(mb_substr($identity, 0, 1)) }}</span>
        </div>
    </div>
</header>
