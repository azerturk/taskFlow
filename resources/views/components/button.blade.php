@props(['variant' => 'primary', 'type' => 'submit'])

@php
    $styles = match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-500',
        default => 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-500',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => 'rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-indigo-500/20 disabled:cursor-not-allowed disabled:opacity-50 '.$styles]) }}>{{ $slot }}</button>
