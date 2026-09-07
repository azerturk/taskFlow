@extends('layouts.app')

@section('title', 'Change Password')
@section('page-title', 'Change Password')

@section('content')
    <section class="max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="text-2xl font-semibold text-slate-950">Change your password</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Your other sessions and all personal API tokens will be revoked after this change.</p>
        <form method="POST" action="{{ route('account.password.update') }}" class="mt-7 space-y-5">@csrf @method('PUT')
            <div><label for="current_password" class="mb-2 block text-sm font-semibold">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="block w-full rounded-xl border border-slate-300 px-4 py-3"><x-form-error field="current_password" /></div>
            <div><label for="password" class="mb-2 block text-sm font-semibold">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required class="block w-full rounded-xl border border-slate-300 px-4 py-3"><x-form-error field="password" /></div>
            <div><label for="password_confirmation" class="mb-2 block text-sm font-semibold">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="block w-full rounded-xl border border-slate-300 px-4 py-3"></div>
            <x-button class="px-5 py-3">Update password</x-button>
        </form>
    </section>
@endsection
