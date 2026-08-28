<x-ds-page>
    <x-ds-page-header title="تقييمات فريقي" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        تعبئة بنود قسم «مدير» فقط لمرؤوسيك. حالة القسم مكتمل/غير مكتمل، مع ملاحظة لكل بند.
        لا يظهر المجموع النهائي ولا درجات قسم الموارد هنا.
    </p>

    @if (! $cycle)
        <x-ds-empty-state message="لا توجد دورة تقييم مفتوحة حالياً." icon="fa-users" />
    @else
        <p class="ds-mb-3">
            الدورة: <strong>{{ $cycle->periodLabel() }}</strong>
            <x-ds-status-badge :status="$cycle->status" />
        </p>

        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th scope="col">الموظف</th>
                    <th scope="col">قسم المدير</th>
                    <th scope="col">الحالة</th>
                    <th scope="col">إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                @php $label = $service->sectionCompletionLabel($row, 'مدير'); @endphp
                <tr wire:key="team-eval-{{ $row->id }}">
                    <td>{{ $row->employee?->name }}</td>
                    <td><x-ds-status-badge :status="$label" /></td>
                    <td><x-ds-status-badge :status="$row->status" /></td>
                    <td>
                        @if ($row->isEditableByScorers())
                            <button type="button" class="ds-link" wire:click="openScoring({{ $row->id }})">تعبئة البنود</button>
                        @else
                            <span class="ds-text-muted">بعد الاعتماد — للموارد فقط</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <x-ds-empty-state message="لا تقييمات لفريقك في الدورة الحالية." icon="fa-user-group" />
                    </td>
                </tr>
            @endforelse
        </x-ds-table>
        {{ $rows->links() }}
    @endif

    <x-ds-modal :show="$scoringId !== null" :title="'بنود المدير — '.($scoringEvaluation?->employee?->name ?? '')" close-action="closeScoring" size="lg">
        @if ($scoringEvaluation)
            <p class="ds-mb-3">
                {{ $scoringEvaluation->cycle?->periodLabel() }}
                — قسم المدير: <x-ds-status-badge :status="$sectionLabel" />
            </p>

            @forelse ($managerItems as $item)
                <div class="ds-mb-3" wire:key="mgr-score-{{ $item->id }}">
                    <p>{{ $item->question_text }}</p>
                    <x-ds-form-group label="الدرجة من 5" :error="$errors->first('scoreInputs.'.$item->id.'.score')">
                        <input type="number" min="1" max="5" class="ds-input ds-ltr-num" wire:model="scoreInputs.{{ $item->id }}.score">
                    </x-ds-form-group>
                    <x-ds-form-group label="ملاحظة">
                        <input type="text" class="ds-input" wire:model="scoreInputs.{{ $item->id }}.note" maxlength="500">
                    </x-ds-form-group>
                </div>
            @empty
                <p class="ds-text-muted">لا بنود مدير في لقطة الدورة</p>
            @endforelse

            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveManagerScores">حفظ</button>
        @endif
    </x-ds-modal>
</x-ds-page>
