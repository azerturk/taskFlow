@extends('layouts.app')

@section('title', $task->number)
@section('page-title', 'Task details')

@section('content')
    <div class="mx-auto max-w-6xl">
        <section class="rounded-3xl bg-slate-950 p-7 text-white shadow-xl shadow-slate-900/10">
            <a href="{{ route('projects.show', $task->project) }}" class="text-sm font-semibold text-indigo-300 hover:text-white">← {{ $task->project->name }}</a>
            <div class="mt-5 flex flex-wrap items-center gap-2"><span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold tracking-wide">{{ $task->number }}</span><button type="button" data-copy-text="{{ $task->number }}" class="rounded-full border border-white/20 px-3 py-1 text-xs font-bold tracking-wide text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white">Copy key</button><span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold tracking-wide">{{ ucfirst($task->type->value) }}</span><x-status-badge :status="$task->status" /><x-priority-badge :priority="$task->priority" /></div>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight">{{ $task->title }}</h2>
            @if($task->labels->isNotEmpty())<div class="mt-4 flex flex-wrap gap-2">@foreach($task->labels as $label)<span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold"><span class="mr-1 inline-block size-2 rounded-full" style="background-color: {{ $label->color }}"></span>{{ $label->name }}</span>@endforeach</div>@endif
            @if ($task->description)<p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-7 text-slate-300">{{ $task->description }}</p>@else<p class="mt-3 text-sm text-slate-400">No detailed description has been added.</p>@endif
            <div class="mt-6 flex flex-wrap gap-2">
                @can('update', $task)<a href="{{ route('tasks.edit', $task) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Edit task</a>@endcan
                @can('delete', $task)<form method="POST" action="{{ route('tasks.destroy', $task) }}" data-confirm="Delete this task? This action cannot be undone.">@csrf @method('DELETE')<button type="submit" class="rounded-xl border border-rose-300/40 px-4 py-2.5 text-sm font-semibold text-rose-100 transition hover:bg-rose-500/10">Delete task</button></form>@endcan
            </div>
        </section>

        @if($task->parent || $task->subtasks->isNotEmpty())<section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h3 class="text-lg font-semibold text-slate-950">Task hierarchy</h3>@if($task->parent)<p class="mt-3 text-sm text-slate-600">Parent: <a class="font-semibold text-indigo-700" href="{{ route('tasks.show', $task->parent) }}">{{ $task->parent->display_key }} · {{ $task->parent->title }}</a></p>@endif@if($task->subtasks->isNotEmpty())<ul class="mt-3 space-y-2">@foreach($task->subtasks as $subtask)<li><a class="text-sm font-semibold text-indigo-700" href="{{ route('tasks.show', $subtask) }}">{{ $subtask->display_key }} · {{ $subtask->title }}</a></li>@endforeach</ul>@endif</section>@endif

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([['label' => 'Project', 'value' => $task->project->name], ['label' => 'Creator', 'value' => $task->creator->name ?: $task->creator->email], ['label' => 'Assignee', 'value' => $task->assignee?->name ?: $task->assignee?->email ?: 'Unassigned'], ['label' => 'Due date', 'value' => $task->due_at?->format('M j, Y') ?? 'Not set'], ['label' => 'Started / completed', 'value' => $task->started_at?->format('M j') ?: ($task->completed_at?->format('M j') ?: 'Not started')]] as $detail)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400">{{ $detail['label'] }}</p><p class="mt-3 break-words font-semibold text-slate-900">{{ $detail['value'] }}</p></article>
            @endforeach
        </section>

        @include('tasks::partials.watchers')

        @if (auth()->user()->can('assign', $task) || auth()->user()->can('changeStatus', $task))
            <section class="mt-7 grid gap-5 lg:grid-cols-2">
                @can('assign', $task)<form method="POST" action="{{ route('tasks.assign', $task) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">@csrf @method('PATCH')<h3 class="text-lg font-semibold text-slate-950">Assignment</h3><p class="mt-1 text-sm text-slate-500">Assign or reassign this task to an available project member.</p><label for="assignee_id" class="sr-only">Assignee</label><select id="assignee_id" name="assignee_id" class="mt-5 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"><option value="">Unassigned</option>@foreach($memberships as $membership)<option value="{{ $membership->user_id }}" @selected($task->assignee_id === $membership->user_id)>{{ $membership->user->name ?: $membership->user->email }}</option>@endforeach</select><button type="submit" class="mt-3 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">Save assignee</button></form>@endcan
                @can('changeStatus', $task)<livewire:tasks.task-status-selector :task="$task" />@endcan
            </section>
        @endif

        <section class="mt-7 grid gap-6 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h3 class="text-lg font-semibold text-slate-950">Comments</h3><p class="mt-1 text-sm text-slate-500">Keep discussion attached to the work it relates to.</p>
                <livewire:tasks.task-comment-form :task="$task" />
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h3 class="text-lg font-semibold text-slate-950">Attachments</h3><p class="mt-1 text-sm text-slate-500">Add relevant files. Supported uploads are images, PDFs, and office documents up to 10 MB.</p>
                @can('uploadAttachment', $task)<form method="POST" enctype="multipart/form-data" action="{{ route('tasks.media.store', $task) }}" class="mt-5 rounded-xl border border-dashed border-slate-300 p-4">@csrf<label for="media" class="block text-sm font-semibold text-slate-700">Choose up to five files</label><div class="mt-3 flex flex-col gap-3 sm:flex-row"><input id="media" type="file" name="media[]" multiple class="min-w-0 flex-1 text-sm text-slate-600"><button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Upload</button></div>@error('media')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</form>@endcan
                <div class="mt-6 space-y-3">@forelse($task->attachments as $attachment)<div class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $attachment->media->original_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $attachment->media->mime_type }} · {{ number_format($attachment->media->size / 1024, 1) }} KB · {{ $attachment->media->uploader->name ?: $attachment->media->uploader->email }} · {{ $attachment->created_at->format('M j, Y') }}</p></div><x-media-links :task="$task" :attachment="$attachment" /></div>@empty<div class="rounded-xl border border-dashed border-slate-300 px-5 py-9 text-center"><p class="font-semibold text-slate-900">No attachments yet.</p><p class="mt-1 text-sm text-slate-500">Uploaded project files will appear here.</p></div>@endforelse</div>
            </article>
        </section>

        @if($canViewActivity)
        <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex items-center justify-between gap-4"><div><h3 class="text-lg font-semibold text-slate-950">Recent activity</h3><p class="mt-1 text-sm text-slate-500">Relevant changes for this task.</p></div>@if($canViewActivity)<a href="{{ route('activity.index', ['task' => $task->id]) }}" class="text-sm font-semibold text-indigo-700">View all activity</a>@endif</div><div class="mt-5 space-y-4">@forelse($activities as $activity)<div class="border-l-2 border-indigo-200 pl-4"><p class="font-semibold text-slate-900">{{ \Modules\Activity\Support\ActivityDisplay::label($activity) }}</p>@if($summary = \Modules\Activity\Support\ActivityDisplay::summary($activity))<p class="mt-1 text-sm text-slate-600">{{ $summary }}</p>@endif<p class="mt-1 text-xs text-slate-500">{{ $activity->causer?->name ?: 'System' }} · {{ $activity->created_at->diffForHumans() }}</p></div>@empty<p class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500">No activity for this task yet.</p>@endforelse</div></section>
        @endif
    </div>
@endsection
