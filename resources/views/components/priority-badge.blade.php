@props(['priority'])

@php
    $value = $priority instanceof \BackedEnum ? $priority->value : $priority;
    $styles = match ($value) {
        'low' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'medium' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'high' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'urgent' => 'bg-rose-50 text-rose-700 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-slate-200',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.$styles]) }}>{{ ucfirst($value) }}</span>
