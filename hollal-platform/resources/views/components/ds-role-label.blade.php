@props(['name' => ''])

@php
    $labels = [
        'Super Admin' => 'مدير النظام',
    ];
@endphp

{{ $labels[$name] ?? $name }}
