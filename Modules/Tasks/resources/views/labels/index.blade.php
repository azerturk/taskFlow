@extends('layouts.app')

@section('title', 'Project labels')
@section('page-title', 'Project labels')

@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('projects.show', $project) }}" class="text-sm font-semibold text-indigo-700">← {{ $project->name }}</a>
    <h2 class="mt-3 text-3xl font-semibold text-slate-950">Labels</h2>
    <p class="mt-2 text-sm text-slate-500">Labels are shared only within this project.</p>
    @can('manageLabels', $project)
    <form method="POST" action="{{ route('projects.labels.store', $project) }}" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-[1fr_auto_auto]">@csrf
        <label class="sr-only" for="new-label-name">Label name</label><input id="new-label-name" name="name" maxlength="80" required value="{{ old('name') }}" placeholder="Label name" class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
        <label class="sr-only" for="new-label-color">Label color</label><select id="new-label-color" name="color" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">@foreach(\Modules\Tasks\Enums\TaskLabelColor::cases() as $color)<option value="{{ $color->value }}">{{ $color->name }}</option>@endforeach</select>
        <button class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white">Add label</button>
    </form>
    @endcan
    <div class="mt-6 space-y-3">@forelse($labels as $label)<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">@can('manageMembers', $project)<form method="POST" action="{{ route('projects.labels.update', [$project, $label]) }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">@csrf @method('PATCH')<input name="name" maxlength="80" required value="{{ $label->name }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm"><select name="color" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">@foreach(\Modules\Tasks\Enums\TaskLabelColor::cases() as $color)<option value="{{ $color->value }}" @selected($label->color === $color->value)>{{ $color->name }}</option>@endforeach</select><button class="rounded-xl border border-indigo-200 px-4 py-3 text-sm font-semibold text-indigo-700">Save</button></form><form method="POST" action="{{ route('projects.labels.destroy', [$project, $label]) }}" class="mt-3">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-700">Delete label</button></form>@else<div class="flex items-center gap-3"><span class="size-3 rounded-full" style="background-color: {{ $label->color }}"></span><span class="font-semibold text-slate-900">{{ $label->name }}</span></div>@endcan</article>@empty<p class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No labels yet.</p>@endforelse</div>
</div>
@endsection
