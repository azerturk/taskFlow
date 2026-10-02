<section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <h3 class="text-lg font-semibold text-slate-950">Watchers</h3>
            <p class="mt-1 text-sm text-slate-500">Watch this task to receive relevant assignment, comment, and status notifications.</p>
        </div>

        @if ($canMutateWatchers)
            @if ($isWatching)
                <form method="POST" action="{{ route('tasks.watchers.destroy', [$task, auth()->id()]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Unwatch task</button>
                </form>
            @else
                <form method="POST" action="{{ route('tasks.watchers.store', $task) }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">Watch task</button>
                </form>
            @endif
        @endif
    </div>

    <div class="mt-5 space-y-2">
        @forelse ($task->watchers as $watcher)
            <div class="flex flex-col gap-3 rounded-xl border border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-slate-900">{{ $watcher->name ?: $watcher->email }}</p>
                    @if ($watcher->id === auth()->id())<p class="mt-1 text-xs text-slate-500">You are watching this task.</p>@endif
                </div>

                @if ($canManageWatchers && $watcher->id !== auth()->id())
                    <form method="POST" action="{{ route('tasks.watchers.destroy', [$task, $watcher]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-semibold text-rose-700">Remove watcher</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 px-5 py-7 text-center text-sm text-slate-500">No one is watching this task yet.</p>
        @endforelse
    </div>

    @if ($canManageWatchers && $watcherCandidates->isNotEmpty())
        <form method="POST" action="{{ route('tasks.watchers.store', $task) }}" class="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-[1fr_auto] sm:items-end">
            @csrf
            <div>
                <label for="watcher-user-id" class="mb-2 block text-sm font-semibold text-slate-700">Watcher member</label>
                <select id="watcher-user-id" name="user_id" required class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                    <option value="">Choose an active project member</option>
                    @foreach ($watcherCandidates as $membership)
                        <option value="{{ $membership->user_id }}">{{ $membership->user->name ?: $membership->user->email }}</option>
                    @endforeach
                </select>
                <x-form-error field="user_id" />
            </div>
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white">Add watcher</button>
        </form>
    @endif
</section>
