@props(['active'])

@php
$classes = ($active ?? false)
            ? 'app-mobile-link app-mobile-link-active'
            : 'app-mobile-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
