@php
    $chunks = is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match) ? $match : null;
@endphp
<span style="display:flex;gap:0.35rem;flex-wrap:wrap">
    <select class="ds-input" wire:change="{{ $method }}({{ ($dateField ?? '') !== '' ? "'".$dateField."', " : '' }}'day', $event.target.value)">
        <option value="">اليوم</option>
        @for ($d = 1; $d <= 31; $d++)
            <option value="{{ sprintf('%02d', $d) }}" @selected(($chunks[3] ?? '') === sprintf('%02d', $d))>{{ $d }}</option>
        @endfor
    </select>
    <select class="ds-input" wire:change="{{ $method }}({{ ($dateField ?? '') !== '' ? "'".$dateField."', " : '' }}'month', $event.target.value)">
        <option value="">الشهر</option>
        @foreach (\App\Support\ArabicStatus::MONTHS as $number => $name)
            <option value="{{ sprintf('%02d', $number) }}" @selected(($chunks[2] ?? '') === sprintf('%02d', $number))>{{ $name }}</option>
        @endforeach
    </select>
    <select class="ds-input" wire:change="{{ $method }}({{ ($dateField ?? '') !== '' ? "'".$dateField."', " : '' }}'year', $event.target.value)">
        <option value="">السنة</option>
        @for ($y = (int) now()->year - 5; $y <= (int) now()->year + 3; $y++)
            <option value="{{ $y }}" @selected(($chunks[1] ?? '') === (string) $y)>{{ $y }}</option>
        @endfor
    </select>
</span>
