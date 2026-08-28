@props([
    'options' => [],
    /** Livewire property name to bind (e.g. grantUserId) */
    'wireModel' => null,
    'placeholder' => 'ابحث للاختيار…',
    'valueKey' => 'id',
    'labelKey' => 'label',
    'subLabelKey' => null,
])

@php
    $optionsList = collect($options)->values()->map(function ($row) use ($valueKey, $labelKey, $subLabelKey) {
        if (is_array($row)) {
            return [
                'value' => $row[$valueKey] ?? null,
                'label' => (string) ($row[$labelKey] ?? ''),
                'sub' => $subLabelKey ? (string) ($row[$subLabelKey] ?? '') : '',
            ];
        }

        return [
            'value' => data_get($row, $valueKey),
            'label' => (string) data_get($row, $labelKey, ''),
            'sub' => $subLabelKey ? (string) data_get($row, $subLabelKey, '') : '',
        ];
    })->all();
@endphp

<div
    {{ $attributes->class(['ds-search-select']) }}
    x-data="dsSearchSelect({
        options: {{ \Illuminate\Support\Js::from($optionsList) }},
        wireModel: {{ \Illuminate\Support\Js::from($wireModel) }},
        placeholder: {{ \Illuminate\Support\Js::from($placeholder) }},
    })"
    @click.outside="close()"
>
    <div class="ds-search-select__control">
        <input
            type="text"
            class="ds-input"
            :value="inputValue"
            @input="onInput($event)"
            @focus="openList()"
            @keydown.escape.prevent="close()"
            @keydown.enter.prevent="selectFirst()"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            :placeholder="displayPlaceholder"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open.toString()"
            aria-autocomplete="list"
        >
        <button
            type="button"
            class="ds-search-select__clear"
            x-show="hasValue"
            x-cloak
            @click.prevent="clear()"
            aria-label="مسح الاختيار"
            title="مسح"
        >&times;</button>
    </div>

    <ul
        class="ds-search-select__list"
        x-show="open"
        x-cloak
        x-transition
        role="listbox"
        @mousedown.prevent
    >
        <template x-for="(opt, idx) in filtered" :key="String(opt.value)">
            <li
                role="option"
                class="ds-search-select__option"
                :class="{ 'is-active': idx === highlight, 'is-selected': String(opt.value) === String(value) }"
                @click="choose(opt)"
                @mouseenter="highlight = idx"
            >
                <strong x-text="opt.label"></strong>
                <span class="ds-text-muted ds-ltr-num" x-show="opt.sub" x-text="opt.sub" style="display:block;font-size:.8rem"></span>
            </li>
        </template>
        <li class="ds-search-select__empty ds-text-muted" x-show="filtered.length === 0">لا توجد نتائج</li>
    </ul>
</div>

{{-- Alpine factory registered in partials/app-shell-scripts (always loaded). --}}
