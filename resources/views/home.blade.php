@extends('layouts.app')

@section('title', 'Home')
@section('page-title', 'Workspace overview')

@section('content')
    @php($identity = filled(auth()->user()->name) ? auth()->user()->name : auth()->user()->email)

    <section class="relative overflow-hidden rounded-3xl bg-slate-950 px-6 py-8 text-white shadow-xl shadow-slate-900/10 sm:px-8 sm:py-10">
        <div class="relative max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-300">TaskFlow workspace</p>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Welcome back, {{ $identity }}.</h2>
            <p class="mt-4 max-w-xl text-base leading-7 text-slate-300">Choose a workspace area to review progress, organise delivery, or catch up on the latest changes.</p>
        </div>
    </section>

    <section class="mt-8">
        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
            <div>
                <p class="text-lg font-semibold text-slate-950">Your workspace</p>
                <p class="mt-1 text-sm text-slate-500">Only areas available to your current access level are shown.</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @can('viewDashboard')
                <a href="{{ route('dashboard.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                    <span class="grid size-10 place-items-center rounded-xl bg-indigo-100 text-sm font-bold text-indigo-700">D</span>
                    <h3 class="mt-6 font-semibold text-slate-950 group-hover:text-indigo-700">Dashboard</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">See your current project and task overview.</p>
                </a>
            @endcan
            @can('projects.view')
                <a href="{{ route('projects.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                    <span class="grid size-10 place-items-center rounded-xl bg-sky-100 text-sm font-bold text-sky-700">P</span>
                    <h3 class="mt-6 font-semibold text-slate-950 group-hover:text-indigo-700">Projects</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Plan initiatives, ownership, and timelines.</p>
                </a>
            @endcan
            @can('tasks.view')
                <a href="{{ route('tasks.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                    <span class="grid size-10 place-items-center rounded-xl bg-violet-100 text-sm font-bold text-violet-700">T</span>
                    <h3 class="mt-6 font-semibold text-slate-950 group-hover:text-indigo-700">Tasks</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Focus on clear ownership and next actions.</p>
                </a>
            @endcan
            @can('activity.view')
                <a href="{{ route('activity.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-100 text-sm font-bold text-emerald-700">A</span>
                    <h3 class="mt-6 font-semibold text-slate-950 group-hover:text-indigo-700">Activity</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Follow meaningful changes across your work.</p>
                </a>
            @endcan
            @can('manageUsers')
                <a href="{{ route('admin.users.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                    <span class="grid size-10 place-items-center rounded-xl bg-amber-100 text-sm font-bold text-amber-800">U</span>
                    <h3 class="mt-6 font-semibold text-slate-950 group-hover:text-indigo-700">User Management</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Provision internal accounts and global roles.</p>
                </a>
            @endcan
        </div>
    </section>
@endsection
