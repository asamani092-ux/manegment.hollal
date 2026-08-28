<x-ds-page>
    <x-ds-page-header title="أرشيف تقييماتي" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        التقييمات المعتمدة أو المؤرشفة تظهر هنا بعد اعتماد الموارد البشرية. لا يُطلب تعليق من الموظف.
    </p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الفترة</th>
                <th scope="col">المجموع</th>
                <th scope="col">الحالة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr wire:key="my-eval-{{ $row->id }}">
                <td>{{ $row->cycle?->periodLabel() ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $row->total_score !== null ? $row->total_score : '—' }}</td>
                <td><x-ds-status-badge :status="$row->status" /></td>
                <td>
                    <button type="button" class="ds-link" wire:click="openView({{ $row->id }})">عرض</button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">
                    <x-ds-empty-state message="لا تقييمات معتمدة بعد." icon="fa-box-archive" />
                </td>
            </tr>
        @endforelse
    </x-ds-table>
    {{ $rows->links() }}

    <x-ds-modal :show="$viewId !== null" :title="$viewEvaluation?->cycle?->periodLabel() ?? 'تفاصيل التقييم'" close-action="closeView" size="lg">
        @if ($viewEvaluation)
            <p class="ds-mb-3">
                <x-ds-status-badge :status="$viewEvaluation->status" />
                @if ($viewEvaluation->total_score !== null)
                    — المجموع: <strong class="ds-ltr-num">{{ $viewEvaluation->total_score }}</strong>
                @endif
                @if ($viewEvaluation->approver)
                    — اعتمده: {{ $viewEvaluation->approver->name }}
                @endif
            </p>
            @foreach ($viewEvaluation->cycle?->items ?? [] as $item)
                @php $score = $viewEvaluation->scores->firstWhere('evaluation_cycle_item_id', $item->id); @endphp
                <div class="ds-mb-2" wire:key="my-item-{{ $item->id }}">
                    <p>
                        <span class="ds-text-muted">[{{ $item->section }}]</span>
                        {{ $item->question_text }}
                    </p>
                    <p class="ds-ltr-num">
                        {{ $score?->score !== null ? $score->score : '—' }} / 5
                        @if ($score?->note) — {{ $score->note }} @endif
                    </p>
                </div>
            @endforeach
        @endif
    </x-ds-modal>
</x-ds-page>
