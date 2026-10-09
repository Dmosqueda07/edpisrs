@props(['active'])

@php
$classes = ($active ?? false)
            ? 'app-nav-link app-nav-link-active'
            : 'app-nav-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
