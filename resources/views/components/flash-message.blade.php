@props(['type' => 'success', 'message'])

@php
    $styles = match ($type) {
        'error' => 'border-rose-200 bg-rose-50 text-rose-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'info' => 'border-sky-200 bg-sky-50 text-sky-900',
        default => 'border-emerald-200 bg-emerald-50 text-emerald-900',
    };
    $label = match ($type) {
        'error' => 'Something needs attention',
        'warning' => 'Please review this',
        'info' => 'Information',
        default => 'Success',
    };
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" class="mb-6 flex items-start gap-3 rounded-2xl border px-4 py-3.5 text-sm shadow-sm {{ $styles }}">
    <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border border-current text-xs font-bold" aria-hidden="true">{{ $type === 'success' ? '✓' : '!' }}</span>
    <div>
        <p class="font-semibold">{{ $label }}</p>
        <p class="mt-0.5 leading-6">{{ $message }}</p>
    </div>
</div>
