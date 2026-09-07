@extends('layouts.app')

@section('title', 'Create User')
@section('page-title', 'Create User')

@section('content')
    <section class="max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-600">Administration</p>
        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Create an internal account</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Passwords are used only to provision access and are never displayed or logged by TaskFlow.</p>
        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-8 space-y-7">@csrf
            @include('admin.users._form', ['creating' => true])
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-semibold text-slate-700">Cancel</a><button type="submit" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500">Save user</button></div>
        </form>
    </section>
@endsection
