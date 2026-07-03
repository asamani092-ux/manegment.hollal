<div>
    @php
        $itemStatusLabels = ['open' => 'مفتوح', 'in_progress' => 'قيد التنفيذ', 'done' => 'منجز'];
    @endphp

    <div class="ds-page-toolbar">
        <div>
            <a href="{{ route('meetings.index') }}" class="ds-link"><i class="fas fa-arrow-right"></i> العودة للاجتماعات</a>
            <h1 class="ds-page-title">{{ $meeting->title }}</h1>
            <p class="ds-text-muted">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</p>
        </div>
        @can('update', $meeting)
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openItemCreate">
                <i class="fas fa-plus"></i> بند جديد
            </button>
        @endcan
    </div>

    <div class="ds-table-wrap">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الموضوع</th>
                    <th>القرار</th>
                    <th>المسؤول</th>
                    <th>تاريخ الاستحقاق</th>
                    <th>الحالة</th>
                    <th>المهمة</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($items as $item)
                <tr wire:key="item-{{ $item->id }}">
                    <td>{{ $item->topic }}</td>
                    <td>{{ $item->decision ?: '—' }}</td>
                    <td>{{ $item->responsible?->name ?? '—' }}</td>
                    <td>{{ $item->due_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $itemStatusLabels[$item->status] ?? $item->status }}</td>
                    <td>{{ $item->task?->title ?? '—' }}</td>
                    <td>
                        @if ($item->decision && ! $item->task_id && auth()->user()->can('update', $meeting) && auth()->user()->can('tasks.create'))
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="convertToTask({{ $item->id }})">
                                <i class="fas fa-tasks"></i> تحويل لمهمة
                            </button>
                        @endif
                        <x-ds-action-icons
                            :show-view="true"
                            :show-edit="auth()->user()->can('update', $meeting)"
                            :show-delete="auth()->user()->can('update', $meeting)"
                            :view-action="'openItemView('.$item->id.')'"
                            :edit-action="'openItemEdit('.$item->id.')'"
                            :delete-action="'deleteItem('.$item->id.')'"
                            delete-confirm="حذف هذا البند؟"
                        />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="ds-text-muted ds-table-empty">لا توجد بنود في المحضر</td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    @if ($showItemModal)
        <div class="ds-modal-overlay" wire:click.self="closeItemModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>
                        @if ($itemViewOnly)
                            عرض بند
                        @elseif ($itemId)
                            تعديل بند
                        @else
                            بند جديد
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeItemModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="الموضوع" :error="$errors->first('topic')">
                        <input type="text" class="ds-input" wire:model="topic" @disabled($itemViewOnly)>
                    </x-ds-form-group>
                    <x-ds-form-group label="ملخص النقاش" :error="$errors->first('discussion_summary')">
                        <textarea class="ds-input" rows="3" wire:model="discussion_summary" @disabled($itemViewOnly)></textarea>
                    </x-ds-form-group>
                    <x-ds-form-group label="القرار" :error="$errors->first('decision')">
                        <textarea class="ds-input" rows="2" wire:model="decision" @disabled($itemViewOnly)></textarea>
                    </x-ds-form-group>
                    <div class="ds-grid-2">
                        <x-ds-form-group label="المسؤول">
                            <select class="ds-input" wire:model="responsible_id" @disabled($itemViewOnly)>
                                <option value="">— بدون —</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="تاريخ الاستحقاق" :error="$errors->first('due_date')">
                            <input type="date" class="ds-input" wire:model="due_date" @disabled($itemViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الحالة" :error="$errors->first('status')">
                            <select class="ds-input" wire:model="status" @disabled($itemViewOnly)>
                                @foreach ($itemStatusLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                    </div>
                </div>
                <div class="ds-modal-footer">
                    @if (! $itemViewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveItem">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeItemModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</div>
