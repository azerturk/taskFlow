@props(['user', 'identity', 'role'])

<div class="fixed inset-0 z-30 hidden bg-slate-950/50 backdrop-blur-sm lg:hidden" data-mobile-nav-backdrop></div>

<aside id="workspace-navigation" data-mobile-nav class="fixed inset-y-0 left-0 z-40 flex w-[17rem] -translate-x-full flex-col overflow-y-auto border-r border-slate-800 bg-slate-950 text-slate-300 shadow-2xl transition-transform duration-200 lg:static lg:min-h-screen lg:translate-x-0 lg:shadow-none">
    <div class="flex items-center justify-between px-5 py-5">
        <a href="{{ route('home') }}" class="flex items-center gap-3 text-white" data-mobile-nav-link>
            <span class="grid size-10 place-items-center rounded-xl bg-indigo-500 text-sm font-black shadow-lg shadow-indigo-500/20">T</span>
            <span><span class="block text-lg font-bold tracking-tight">TaskFlow</span><span class="block text-xs text-slate-400">Internal workspace</span></span>
        </a>
        <button type="button" class="grid size-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-400 lg:hidden" aria-label="Close navigation" aria-controls="workspace-navigation" data-mobile-nav-close>×</button>
    </div>

    <nav class="space-y-1 px-3" aria-label="Workspace navigation">
        @php($linkClass = 'flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-indigo-300')
        <a href="{{ route('home') }}" data-mobile-nav-link aria-current="{{ request()->routeIs('home') ? 'page' : 'false' }}" @class([$linkClass, 'bg-indigo-500 text-white shadow-sm shadow-indigo-950/30' => request()->routeIs('home'), 'text-slate-300 hover:bg-slate-800 hover:text-white' => !request()->routeIs('home')])>Home</a>
        @can('viewDashboard')<a href="{{ route('dashboard.index') }}" data-mobile-nav-link aria-current="{{ request()->routeIs('dashboard.*') ? 'page' : 'false' }}" @class([$linkClass, 'bg-indigo-500 text-white shadow-sm shadow-indigo-950/30' => request()->routeIs('dashboard.*'), 'text-slate-300 hover:bg-slate-800 hover:text-white' => !request()->routeIs('dashboard.*')])>Dashboard</a>@endcan
        @can('projects.view')<a href="{{ route('projects.index') }}" data-mobile-nav-link aria-current="{{ request()->routeIs('projects.*') ? 'page' : 'false' }}" @class([$linkClass, 'bg-indigo-500 text-white shadow-sm shadow-indigo-950/30' => request()->routeIs('projects.*'), 'text-slate-300 hover:bg-slate-800 hover:text-white' => !request()->routeIs('projects.*')])>Projects</a>@endcan
        @can('tasks.view')<a href="{{ route('tasks.index') }}" data-mobile-nav-link aria-current="{{ request()->routeIs('tasks.*') ? 'page' : 'false' }}" @class([$linkClass, 'bg-indigo-500 text-white shadow-sm shadow-indigo-950/30' => request()->routeIs('tasks.*'), 'text-slate-300 hover:bg-slate-800 hover:text-white' => !request()->routeIs('tasks.*')])>Tasks</a>@endcan
        @can('activity.view')<a href="{{ route('activity.index') }}" data-mobile-nav-link aria-current="{{ request()->routeIs('activity.*') ? 'page' : 'false' }}" @class([$linkClass, 'bg-indigo-500 text-white shadow-sm shadow-indigo-950/30' => request()->routeIs('activity.*'), 'text-slate-300 hover:bg-slate-800 hover:text-white' => !request()->routeIs('activity.*')])>Activity</a>@endcan
        @can('manageUsers')
            <div class="my-4 border-t border-slate-800"></div>
            <a href="{{ route('admin.users.index') }}" data-mobile-nav-link aria-current="{{ request()->routeIs('admin.users.*') ? 'page' : 'false' }}" @class([$linkClass, 'bg-indigo-500 text-white shadow-sm shadow-indigo-950/30' => request()->routeIs('admin.users.*'), 'text-slate-300 hover:bg-slate-800 hover:text-white' => !request()->routeIs('admin.users.*')])>User Management</a>
        @endcan
    </nav>

    <div class="mt-auto p-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="truncate text-sm font-semibold text-white">{{ $identity }}</p>
            <p class="mt-1 text-xs font-medium text-indigo-300">{{ $role ? ucwords(str_replace('_', ' ', $role)) : 'Workspace member' }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf
                <x-button variant="secondary" class="w-full border-slate-700 text-slate-200 hover:border-slate-600 hover:bg-slate-800">Sign out</x-button>
            </form>
        </div>
    </div>
</aside>
