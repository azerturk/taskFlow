@php($editing = ($mode ?? 'create') === 'edit')
@php($submitLabel = $submitLabel ?? ($editing ? 'Save changes' : 'Create project'))

<form method="POST" action="{{ $editing ? route('projects.update', $project) : route('projects.store') }}" class="space-y-6">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div>
        <label for="name" class="mb-2 block text-sm font-semibold text-slate-800">Project name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $project->name ?? '') }}" required autofocus class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('name') border-rose-400 @enderror">
        @error('name')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="key" class="mb-2 block text-sm font-semibold text-slate-800">Project key</label>
        <input id="key" name="key" type="text" value="{{ old('key', $project->key ?? '') }}" required maxlength="10" pattern="[A-Z][A-Z0-9]{1,9}" @disabled(isset($project) && $project->next_issue_number > 1) class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-mono uppercase text-slate-900 @error('key') border-rose-400 @enderror">
        <p class="mt-2 text-xs text-slate-500">2–10 böyük hərf/rəqəm. İlk issue yarandıqdan sonra dəyişdirilə bilməz.</p>
        @if (isset($project) && $project->next_issue_number > 1)<input type="hidden" name="key" value="{{ $project->key }}">@endif
        @error('key')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="mb-2 block text-sm font-semibold text-slate-800">Description <span class="font-normal text-slate-400">(optional)</span></label>
        <textarea id="description" name="description" rows="6" class="block w-full resize-y rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('description') border-rose-400 @enderror">{{ old('description', $project->description ?? '') }}</textarea>
        @error('description')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="starts_at" class="mb-2 block text-sm font-semibold text-slate-800">Start date <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="starts_at" name="starts_at" type="date" value="{{ old('starts_at', isset($project) && $project->starts_at ? $project->starts_at->format('Y-m-d') : '') }}" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('starts_at') border-rose-400 @enderror">
            @error('starts_at')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="due_at" class="mb-2 block text-sm font-semibold text-slate-800">Due date <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="due_at" name="due_at" type="date" value="{{ old('due_at', isset($project) && $project->due_at ? $project->due_at->format('Y-m-d') : '') }}" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('due_at') border-rose-400 @enderror">
            @error('due_at')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6">
        <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/20">{{ $submitLabel }}</button>
        <a href="{{ $editing ? route('projects.show', $project) : route('projects.index') }}" class="rounded-xl px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancel</a>
    </div>
</form>
