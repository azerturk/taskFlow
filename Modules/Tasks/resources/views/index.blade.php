@extends('layouts.app')

@section('title', 'Tasks')
@section('page-title', 'Tasks')

@section('content')
    <section class="rounded-3xl bg-slate-950 p-7 text-white shadow-xl shadow-slate-900/10">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-300">Task workspace</p>
        <h2 class="mt-3 text-3xl font-semibold tracking-tight">Make project work visible and actionable.</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Filter by ownership, project, priority, status, and delivery date without losing the wider context.</p>
    </section>

    <livewire:tasks.task-filters />
@endsection
