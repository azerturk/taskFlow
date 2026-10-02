@extends('layouts.app')

@section('title', 'Edit User')
@section('page-title', 'Edit User')

@section('content')
    <section class="max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-600">Administration</p>
        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Edit {{ $managedUser->name }}</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Manage account identity and global TaskFlow access. Project membership roles remain independent and are not edited here.</p>
        <form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="mt-8 space-y-7">@csrf @method('PUT')
            @include('admin.users._form', ['creating' => false])
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-semibold text-slate-700">Cancel</a><button type="submit" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500">Save changes</button></div>
        </form>
        <div class="mt-8 border-t border-slate-200 pt-7">
            <h3 class="text-lg font-semibold text-slate-950">Account access</h3>
            <p class="mt-2 text-sm text-slate-500">Status: <strong>{{ $managedUser->status->value }}</strong>. Suspending revokes sessions and tokens and unassigns open work; it does not remove project history.</p>
            @error('status')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
            <div class="mt-4 flex flex-wrap gap-3">
                @if ($managedUser->isActive())
                    <form method="POST" action="{{ route('admin.users.suspend', $managedUser) }}">@csrf @method('PUT')<button class="rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white">Suspend account</button></form>
                @else
                    <form method="POST" action="{{ route('admin.users.reactivate', $managedUser) }}">@csrf @method('PUT')<button class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white">Reactivate account</button></form>
                @endif
            </div>
        </div>
        <div class="mt-8 border-t border-slate-200 pt-7">
            <h3 class="text-lg font-semibold text-slate-950">Reset password</h3>
            <form method="POST" action="{{ route('admin.users.password.reset', $managedUser) }}" class="mt-4 grid gap-4 sm:grid-cols-2">@csrf @method('PUT')
                <div><label for="password" class="mb-2 block text-sm font-semibold">Temporary password</label><input id="password" name="password" type="password" autocomplete="new-password" required class="block w-full rounded-xl border border-slate-300 px-4 py-3">@error('password')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label for="password_confirmation" class="mb-2 block text-sm font-semibold">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="block w-full rounded-xl border border-slate-300 px-4 py-3"></div>
                <div class="sm:col-span-2"><button class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">Reset password and revoke access</button></div>
            </form>
        </div>
    </section>
@endsection
