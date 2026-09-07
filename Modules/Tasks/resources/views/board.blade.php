@extends('layouts.app')

@section('title', 'Board')
@section('page-title', 'Project board')

@section('content')
<div class="mx-auto max-w-[100rem]">
    <a href="{{ route('projects.show', $project) }}" class="text-sm font-semibold text-indigo-700">← {{ $project->name }}</a>
    <form class="mt-4 flex gap-2">
        <label class="sr-only" for="board-search">Search board</label>
        <input id="board-search" name="q" value="{{ $query }}" placeholder="Search board" class="rounded-xl border border-slate-300 px-4 py-2 focus:outline-none focus:ring-4 focus:ring-indigo-500/15">
        <x-button variant="secondary">Filter</x-button>
    </form>

    <p class="mt-4 text-sm font-medium" role="status" aria-live="polite" data-board-feedback hidden></p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6" data-board>
        @foreach($columns as $statusValue => $column)
            <section class="min-w-0 rounded-2xl bg-slate-100 p-3" data-column="{{ $statusValue }}">
                <h2 class="px-2 text-sm font-bold text-slate-700">
                    {{ ucwords(str_replace('_', ' ', $statusValue)) }}
                    <span class="text-slate-400">{{ $column->count() }}</span>
                </h2>
                <div class="mt-3 min-h-16 space-y-3" data-cards>
                    @foreach($column as $task)
                        @php($taskTransitions = $transitions[$task->id] ?? [])
                        @php($transitionValues = array_map(fn ($status) => $status->value, $taskTransitions))
                        <article
                            draggable="{{ $canReorder || $taskTransitions !== [] ? 'true' : 'false' }}"
                            tabindex="0"
                            data-board-card
                            data-task="{{ $task->id }}"
                            data-version="{{ $task->version }}"
                            data-status-url="{{ route('tasks.status', $task) }}"
                            data-rank-url="{{ $canReorder ? route('tasks.reorder', $task) : '' }}"
                            data-transitions='@json($transitionValues)'
                            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none focus:ring-4 focus:ring-indigo-500/20"
                        >
                            <a href="{{ route('tasks.show', $task) }}" class="font-semibold text-indigo-700">{{ $task->number }}</a>
                            <p class="mt-1 text-sm font-semibold">{{ $task->title }}</p>
                            <p class="mt-2 text-xs text-slate-500">{{ $task->assignee?->name ?: 'Unassigned' }}</p>

                            @if($taskTransitions !== [])
                                <form method="POST" action="{{ route('tasks.status', $task) }}" class="mt-3 flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="expected_version" value="{{ $task->version }}">
                                    <label class="sr-only" for="status-{{ $task->id }}">Move {{ $task->number }}</label>
                                    <select id="status-{{ $task->id }}" name="status" class="min-w-0 flex-1 rounded border border-slate-300 text-xs">
                                        @foreach($taskTransitions as $option)
                                            <option value="{{ $option->value }}">{{ ucwords(str_replace('_', ' ', $option->value)) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="text-xs font-semibold text-indigo-700">Move</button>
                                </form>
                            @endif

                            @if($canReorder && $column->count() > 1)
                                <form method="POST" action="{{ route('tasks.reorder', $task) }}" class="mt-2 flex gap-2" data-rank-form>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="expected_version" value="{{ $task->version }}">
                                    <label class="sr-only" for="rank-{{ $task->id }}">Reorder {{ $task->number }}</label>
                                    <select id="rank-{{ $task->id }}" name="after_task_id" class="min-w-0 flex-1 rounded border border-slate-300 text-xs">
                                        <option value="">Move to end</option>
                                        @foreach($column as $neighbor)
                                            @if($neighbor->id !== $task->id)
                                                <option value="{{ $neighbor->id }}">Before {{ $neighbor->number }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <button class="text-xs font-semibold text-indigo-700">Reorder</button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
@endsection
