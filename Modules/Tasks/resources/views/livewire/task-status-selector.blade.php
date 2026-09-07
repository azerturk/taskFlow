<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" wire:loading.class="opacity-60">
    <h3 class="text-lg font-semibold text-slate-950">Move task forward</h3>
    <p class="mt-1 text-sm text-slate-500">Only transitions permitted for your role are available.</p>

    @if ($availableStatuses === [])
        <p class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">No status transition is currently available.</p>
    @else
        <form method="POST" action="{{ route('tasks.status', $task) }}" wire:submit="change" class="mt-5">@csrf @method('PATCH')
            <input type="hidden" name="expected_version" wire:model="expectedVersion" value="{{ $expectedVersion }}">
            <label for="task-status-{{ $taskId }}" class="sr-only">New status</label>
            <select id="task-status-{{ $taskId }}" name="status" wire:model="status" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15">
                <option value="">Choose a status</option>
                @foreach($availableStatuses as $availableStatus)<option value="{{ $availableStatus->value }}">{{ ucwords(str_replace('_', ' ', $availableStatus->value)) }}</option>@endforeach
            </select>
            <x-form-error field="status" />
            <div class="mt-3 flex items-center gap-3"><x-button>Update status</x-button><span wire:loading wire:target="change" class="text-sm font-medium text-indigo-700" role="status">Updating…</span></div>
        </form>
    @endif

    @if ($success)<p class="mt-4 text-sm font-semibold text-emerald-700" role="status">{{ $success }}</p>@endif
</div>
