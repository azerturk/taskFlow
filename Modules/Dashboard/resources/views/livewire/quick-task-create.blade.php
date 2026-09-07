<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div>
        <h3 class="text-lg font-semibold text-slate-950">Quick task</h3>
        <p class="mt-1 text-sm text-slate-500">Create a backlog item in a project you can work in.</p>
    </div>

    @if ($success)<p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" role="status">{{ $success }}</p>@endif

    <form method="POST" action="{{ $project ? route('tasks.store', $project) : '#' }}" wire:submit="submit" class="mt-5 space-y-4">
        @csrf
        @if ($fixedProjectId === null)
            <div><label for="quick-project" class="mb-2 block text-sm font-semibold text-slate-700">Project</label><select id="quick-project" wire:model.live="projectId" name="project_id" required class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"><option value="">Choose a project</option>@foreach($projects as $choice)<option value="{{ $choice->id }}">{{ $choice->key }} · {{ $choice->name }}</option>@endforeach</select>@error('projectId')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
        @else
            <input type="hidden" name="project_id" value="{{ $projectId }}">
        @endif
        <div><label for="quick-title" class="mb-2 block text-sm font-semibold text-slate-700">Task title</label><input id="quick-title" wire:model="title" name="title" required maxlength="180" class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">@error('title')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="quick-type" class="mb-2 block text-sm font-semibold text-slate-700">Work type</label><select id="quick-type" wire:model="type" name="type" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">@foreach($types as $choice)<option value="{{ $choice->value }}">{{ ucfirst($choice->value) }}</option>@endforeach</select>@error('type')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
            <div><label for="quick-priority" class="mb-2 block text-sm font-semibold text-slate-700">Priority</label><select id="quick-priority" wire:model="priority" name="priority" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">@foreach($priorities as $choice)<option value="{{ $choice->value }}">{{ ucfirst($choice->value) }}</option>@endforeach</select>@error('priority')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
            <div><label for="quick-assignee" class="mb-2 block text-sm font-semibold text-slate-700">Assignee</label><select id="quick-assignee" wire:model="assigneeId" name="assignee_id" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"><option value="">Unassigned</option>@foreach($options['memberships'] as $membership)<option value="{{ $membership->user_id }}">{{ $membership->user->name ?: $membership->user->email }}</option>@endforeach</select>@error('assigneeId')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
            <div><label for="quick-parent" class="mb-2 block text-sm font-semibold text-slate-700">Parent task <span class="font-normal text-slate-400">(subtask only)</span></label><select id="quick-parent" wire:model="parentId" name="parent_id" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"><option value="">No parent</option>@foreach($options['parents'] as $parent)<option value="{{ $parent->id }}">{{ $parent->display_key }} · {{ $parent->title }}</option>@endforeach</select>@error('parentId')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
        </div>
        <fieldset><legend class="mb-2 block text-sm font-semibold text-slate-700">Labels <span class="font-normal text-slate-400">(optional)</span></legend><div class="flex flex-wrap gap-2">@forelse($options['labels'] as $label)<label class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm text-slate-700"><input wire:model="labelIds" type="checkbox" name="label_ids[]" value="{{ $label->id }}"><span class="size-2 rounded-full" style="background-color: {{ $label->color }}"></span>{{ $label->name }}</label>@empty<p class="text-sm text-slate-500">No project labels have been created.</p>@endforelse</div>@error('labelIds')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</fieldset>
        <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="submit">Create task</span><span wire:loading wire:target="submit">Creating…</span></button>
    </form>
</article>
