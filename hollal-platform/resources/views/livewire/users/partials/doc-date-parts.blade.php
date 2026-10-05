@php
    $chunks = is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match) ? $match : null;
    $year = $chunks[1] ?? '';
    $month = $chunks[2] ?? '';
    $day = $chunks[3] ?? '';
@endphp
<span style="display:flex;gap:0.35rem;flex-wrap:wrap">
    <select class="ds-input" wire:change="setDocPart('{{ $field }}', 'day', $event.target.value)">
        <option value="">اليوم</option>
        @for ($d = 1; $d <= 31; $d++)
            <option value="{{ sprintf('%02d', $d) }}" @selected($day === sprintf('%02d', $d))>{{ $d }}</option>
        @endfor
    </select>
    <select class="ds-input" wire:change="setDocPart('{{ $field }}', 'month', $event.target.value)">
        <option value="">الشهر</option>
        @foreach (\App\Support\ArabicStatus::MONTHS as $number => $name)
            <option value="{{ sprintf('%02d', $number) }}" @selected($month === sprintf('%02d', $number))>{{ $name }}</option>
        @endforeach
    </select>
    <select class="ds-input" wire:change="setDocPart('{{ $field }}', 'year', $event.target.value)">
        <option value="">السنة</option>
        @for ($y = (int) now()->year - 20; $y <= (int) now()->year + 6; $y++)
            <option value="{{ $y }}" @selected((string) $year === (string) $y)>{{ $y }}</option>
        @endfor
    </select>
</span>
