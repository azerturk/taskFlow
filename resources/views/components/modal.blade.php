<dialog class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/50" aria-labelledby="confirmation-title" data-confirm-modal>
    <form method="dialog" class="p-6">
        <h2 id="confirmation-title" class="text-lg font-semibold">Confirm action</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600" data-confirm-message></p>
        <div class="mt-6 flex justify-end gap-3">
            <x-button type="submit" variant="secondary" value="cancel" data-confirm-cancel>Cancel</x-button>
            <x-button type="submit" variant="danger" value="confirm" data-confirm-submit>Continue</x-button>
        </div>
    </form>
</dialog>

<dialog class="h-[min(90vh,48rem)] w-[min(92vw,70rem)] rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/50" aria-labelledby="preview-title" data-preview-modal>
    <div class="flex h-full flex-col p-4 sm:p-6"><div class="flex items-center justify-between gap-4"><h2 id="preview-title" class="truncate text-lg font-semibold" data-preview-title>Attachment preview</h2><button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" data-preview-close>Close</button></div><iframe class="mt-4 min-h-0 flex-1 rounded-xl border border-slate-200" title="Attachment preview" data-preview-frame></iframe></div>
</dialog>
