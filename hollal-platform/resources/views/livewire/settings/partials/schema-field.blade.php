@php
    $type = $field['type'] ?? 'string';
    $name = $field['name'];
    $options = $field['options'] ?? [];
    $columns = $field['columns'] ?? [];
@endphp
<x-ds-form-group :label="$field['label_ar'] ?? $name">
    @if ($options !== [])
        <select class="ds-input" wire:model="itemAttributes.{{ $name }}">
            <option value="">—</option>
            @foreach ($options as $option)
                <option value="{{ $option['value'] }}">{{ $option['label_ar'] }}</option>
            @endforeach
        </select>
    @elseif ($type === 'integer')
        <input class="ds-input" type="number" wire:model="itemAttributes.{{ $name }}">
    @elseif ($type === 'boolean')
        <select class="ds-input" wire:model="itemAttributes.{{ $name }}">
            <option value="0">لا</option>
            <option value="1">نعم</option>
        </select>
    @elseif ($type === 'date')
        @include('livewire.settings.partials.arabic-date', ['value' => $itemAttributes[$name] ?? '', 'method' => 'setSchemaDate', 'dateField' => $name])
    @elseif ($type === 'json')
        @foreach (($itemAttributes[$name] ?? []) as $index => $row)
            <div style="display:flex;gap:0.35rem;flex-wrap:wrap;margin-bottom:0.3rem" wire:key="json-{{ $name }}-{{ $index }}">
                @if ($columns !== [])
                    @foreach ($columns as $column)
                        <label>{{ $column['label_ar'] }}
                            @if (($column['type'] ?? '') === 'integer')
                                <input class="ds-input" type="number" wire:model="itemAttributes.{{ $name }}.{{ $index }}.{{ $column['name'] }}">
                            @else
                                <input class="ds-input" wire:model="itemAttributes.{{ $name }}.{{ $index }}.{{ $column['name'] }}">
                            @endif
                        </label>
                    @endforeach
                @else
                    <input class="ds-input" wire:model="itemAttributes.{{ $name }}.{{ $index }}.text">
                @endif
                <button type="button" class="ds-btn ds-btn-sm" wire:click="removeJsonRow('{{ $name }}', {{ $index }})">حذف</button>
            </div>
        @endforeach
        <button type="button" class="ds-btn ds-btn-sm" wire:click="addJsonRow('{{ $name }}')">إضافة صف</button>
    @elseif (($field['label_ar'] ?? '') === 'النص' || $name === 'body_ar' || $name === 'description')
        <textarea class="ds-input" wire:model="itemAttributes.{{ $name }}"></textarea>
    @else
        <input class="ds-input" wire:model="itemAttributes.{{ $name }}">
    @endif
</x-ds-form-group>
