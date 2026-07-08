@props(['name' => ''])

@php
    $labels = config('permission_labels.labels', []);
@endphp

{{ $labels[$name] ?? $name }}
