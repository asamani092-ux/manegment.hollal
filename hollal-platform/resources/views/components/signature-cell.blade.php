@props(['path' => null, 'text' => null])

@php
    $src = null;

    if ($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
        $mime = \Illuminate\Support\Facades\Storage::disk('local')->mimeType($path) ?: 'image/png';
        $src = 'data:'.$mime.';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($path));
    }
@endphp

@if ($src)
    <img src="{{ $src }}" alt="التوقيع" style="max-height:40px;max-width:150px;object-fit:contain">
@elseif ($text)
    {{ $text }}
@else
    —
@endif
