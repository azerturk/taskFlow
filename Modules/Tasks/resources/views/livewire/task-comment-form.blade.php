<div>
    @can('comment', $task)
        <form method="POST" action="{{ route('tasks.comments.store', $task) }}" wire:submit="submit" class="mt-5">@csrf
            <label for="task-comment-{{ $taskId }}" class="sr-only">Comment</label>
            <textarea id="task-comment-{{ $taskId }}" name="body" wire:model="body" rows="4" maxlength="5000" data-character-counter aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}" class="block w-full resize-y rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('body') border-rose-400 @enderror" placeholder="Add a comment" wire:loading.attr="disabled" wire:target="submit"></textarea>
            <div class="mt-2 flex items-center justify-between gap-3"><p class="text-xs text-slate-400"><span data-character-count-for="task-comment-{{ $taskId }}" aria-live="polite"></span><span class="sr-only"> character limit.</span></p><div class="flex items-center gap-3"><span wire:loading wire:target="submit" class="text-xs font-semibold text-indigo-700" role="status">Adding…</span><x-button wire:loading.attr="disabled" wire:target="submit">Add comment</x-button></div></div>
            <x-form-error field="body" />
        </form>
    @endcan

    @if ($success)<p class="mt-3 text-sm font-semibold text-emerald-700" role="status">{{ $success }}</p>@endif

    <div class="mt-6 space-y-4" wire:loading.class="opacity-60">@forelse($comments as $comment)<div class="rounded-xl border border-slate-200 p-4"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-900">{{ $comment->user->name ?: $comment->user->email }}</p><p class="mt-1 text-xs text-slate-500">{{ $comment->created_at->diffForHumans() }}</p></div>@can('deleteComment', [$task, $comment])<form method="POST" action="{{ route('tasks.comments.destroy', [$task, $comment]) }}" data-confirm="Delete this comment?">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-rose-700 hover:text-rose-600">Delete</button></form>@endcan</div><p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $comment->body }}</p></div>@empty<div class="rounded-xl border border-dashed border-slate-300 px-5 py-9 text-center"><p class="font-semibold text-slate-900">No comments yet.</p><p class="mt-1 text-sm text-slate-500">Start the conversation when there is useful context to add.</p></div>@endforelse</div>
</div>
