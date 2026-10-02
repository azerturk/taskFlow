@extends('layouts.app')

@section('title', 'User Management')
@section('page-title', 'User Management')

@section('content')
    <section class="flex flex-col justify-between gap-5 rounded-3xl bg-slate-950 p-6 text-white shadow-xl shadow-slate-900/10 sm:flex-row sm:items-end sm:p-8">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-300">Administration</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">Manage internal workspace access.</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">Provision accounts and maintain global TaskFlow roles without changing project-specific memberships.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-950 shadow-lg transition hover:bg-slate-100">Create user</a>
    </section>

    <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
            <label class="sr-only" for="search">Search users</label>
            <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Search by name or email" class="rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15">
            <label class="sr-only" for="role">Global role</label>
            <select id="role" name="role" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15">
                <option value="">All global roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ ucwords(str_replace('_', ' ', $role->value)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700">Filter</button>
        </form>
    </section>

    @if ($users->isEmpty())
        <section class="mt-7 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-indigo-100 text-lg font-bold text-indigo-700">U</span>
            <h3 class="mt-5 text-lg font-semibold text-slate-950">No users found</h3>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Try a different filter, or create an internal account for your workspace.</p>
            <a href="{{ route('admin.users.create') }}" class="mt-6 inline-flex rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">Create user</a>
        </section>
    @else
        <section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-6 py-4">User</th><th class="px-6 py-4">Global role</th><th class="px-6 py-4">Account</th><th class="px-6 py-4">Project memberships</th><th class="px-6 py-4">Created</th><th class="px-6 py-4"><span class="sr-only">Edit</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($users as $managedUser)
                            @php($role = $managedUser->roles->first()?->name)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-6 py-4"><p class="font-semibold text-slate-900">{{ $managedUser->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $managedUser->email }}</p></td>
                                <td class="px-6 py-4"><span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ $role ? ucwords(str_replace('_', ' ', $role)) : 'No role' }}</span></td>
                                <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $managedUser->isActive() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $managedUser->status->value }}</span></td>
                                <td class="px-6 py-4 text-slate-600">{{ $managedUser->project_memberships_count }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $managedUser->created_at?->format('M j, Y') }}</td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('admin.users.edit', $managedUser) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-indigo-300 hover:text-indigo-700">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <div class="mt-6">{{ $users->links() }}</div>
    @endif
@endsection
