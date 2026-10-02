@extends('layouts.app')
@section('title', 'New task') @section('page-title', 'New task')
@section('content')<div class="mx-auto max-w-3xl"><p class="text-sm font-semibold uppercase tracking-widest text-indigo-600">{{ $project->name }}</p><h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Turn a project goal into focused work.</h2><p class="mt-2 text-sm leading-6 text-slate-500">Create a clear, actionable task for this active project.</p><div class="mt-7 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">@include('tasks::_form')</div></div>@endsection
