<x-ds-page>
    <x-ds-page-header title="القياس والأثر" :show-button="false" />

    <p class="ds-text-muted" style="margin-bottom:1rem;">
        النماذج أدناه للقوالب المركزية. نتائج القياس لكل مشروع تُدار من تبويب
        <strong>القياس والأثر</strong> داخل
        <a class="ds-link" href="{{ route('projects.index') }}">تنفيذ المشروع</a>.
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

    <h2 class="ds-section-heading">نماذج القياس</h2>
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

    <h2 class="ds-section-heading" style="margin-top:1.5rem;">أحدث نتائج الاختبارات</h2>
    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">المشروع</th>
                <th scope="col">النموذج</th>
                <th scope="col">المرحلة</th>
                <th scope="col">النتيجة</th>
                <th scope="col">التاريخ</th>
                <th scope="col">فتح</th>
            </tr>
        </x-slot:head>
        @forelse ($responses as $response)
            <tr wire:key="mr-{{ $response->id }}">
                <td>{{ $response->project?->name ?? '—' }}</td>
                <td>{{ $response->form?->title ?? '—' }}</td>
                <td>{{ $response->phase }}</td>
                <td class="ds-ltr-num">{{ number_format($response->percent(), 1) }}%</td>
                <td class="ds-ltr-num">{{ $response->created_at?->format('Y-m-d') }}</td>
                <td>
                    @if ($response->project_id)
                        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('projects.execution', $response->project_id) }}?tab=measurement">التنفيذ</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6"><x-ds-empty-state message="لا توجد نتائج بعد" icon="fa-chart-bar" /></td>
            </tr>
        @endforelse
    </x-ds-table>
</x-ds-page>
