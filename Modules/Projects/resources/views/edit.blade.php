@extends('layouts.app')

@section('title', 'Edit project')
@section('page-title', 'Edit project')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-7">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-600">Projects</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Refine project</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">Keep the project brief and timeline current for everyone following the work.</p>
        </div>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @include('projects::_form', ['project' => $project, 'mode' => 'edit', 'submitLabel' => 'Save changes'])
        </section>
    </div>
@endsection
