@extends('layouts.app')

@section('title', 'Backlog')
@section('page-title', 'Backlog')

@section('content')
<div class="mx-auto max-w-5xl">
    <a class="text-sm font-semibold text-indigo-700" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
    <h2 class="mt-3 text-3xl font-semibold">Project backlog</h2>
    <form class="mt-4 flex gap-2">
        <label class="sr-only" for="backlog-search">Search backlog</label>
        <input id="backlog-search" name="q" value="{{ $query }}" placeholder="Search backlog" class="rounded-xl border border-slate-300 px-4 py-2 focus:outline-none focus:ring-4 focus:ring-indigo-500/15">
        <x-button variant="secondary">Filter</x-button>
    </form>

    <div class="mt-6 space-y-3">
        @forelse($tasks as $task)
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex justify-between gap-4">
                    <a class="font-semibold text-indigo-700" href="{{ route('tasks.show', $task) }}">{{ $task->number }} · {{ $task->title }}</a>
                    <span class="shrink-0 text-sm text-slate-500">Backlog · #{{ $task->rank }}</span>
                </div>

                @if($canReorder && $tasks->count() > 1)
                    <form method="POST" action="{{ route('tasks.reorder', $task) }}" class="mt-3 flex gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="expected_version" value="{{ $task->version }}">
                        <label class="sr-only" for="backlog-rank-{{ $task->id }}">Reorder {{ $task->number }}</label>
                        <select id="backlog-rank-{{ $task->id }}" name="after_task_id" class="min-w-0 flex-1 rounded border border-slate-300 text-sm">
                            <option value="">Move to end</option>
                            @foreach($tasks as $neighbor)
                                @if($neighbor->id !== $task->id)
                                    <option value="{{ $neighbor->id }}">Before {{ $neighbor->number }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button class="text-sm font-semibold text-indigo-700">Reorder</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="text-slate-500">No backlog work items.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $tasks->links() }}</div>
</div>
@endsection
