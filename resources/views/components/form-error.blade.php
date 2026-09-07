@props(['field'])

@error($field)
    <p {{ $attributes->merge(['class' => 'mt-2 text-sm font-medium text-rose-600']) }} role="alert">{{ $message }}</p>
@enderror
