<x-ds-page>
    <x-ds-page-header title="القياس والأثر" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        لتسجيل القياس القبلي أو البعدي لمشروع: افتح المشروع ثم «مساحة التنفيذ» → تبويب القياس والأثر واختر المرحلة (قبلي/بعدي).
    </p>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="measurement-program">البرنامج</label>
            <select id="measurement-program" class="ds-input" wire:model.live="programFilter">
                <option value="">— الكل —</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}">{{ $program->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="measurement-kind">النوع</label>
            <select id="measurement-kind" class="ds-input" wire:model.live="kindFilter">
                <option value="">— الكل —</option>
                @foreach ($kindOptions as $kind)
                    <option value="{{ $kind }}">{{ $kind }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">النموذج</th>
                <th scope="col">البرنامج</th>
                <th scope="col">النوع</th>
                <th scope="col">التاريخ</th>
            </tr>
        </x-slot:head>
        @forelse ($forms as $form)
            <tr wire:key="mf-{{ $form->id }}">
                <td>{{ $form->title }}</td>
                <td>{{ $form->program?->name ?? '—' }}</td>
                <td>{{ $form->kind }}</td>
                <td class="ds-ltr-num">{{ $form->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4"><x-ds-empty-state message="لا توجد نماذج قياس" icon="fa-chart-line" /></td>
            </tr>
        @endforelse
    </x-ds-table>

    {{ $forms->links() }}
</x-ds-page>
