@props(['status', 'kind' => 'task'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : $status;
    $styles = $kind === 'project'
        ? match ($value) {
            'draft' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'active' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'archived' => 'bg-amber-50 text-amber-800 ring-amber-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        }
        : match ($value) {
            'todo' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'in_progress' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'review' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'done' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.$styles]) }}>{{ ucwords(str_replace('_', ' ', $value)) }}</span>
