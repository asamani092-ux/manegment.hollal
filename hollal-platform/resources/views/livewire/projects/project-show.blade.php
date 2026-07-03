<div>
    @php
        $statusLabels = ['active' => 'نشط', 'completed' => 'مكتمل', 'on_hold' => 'متوقف'];
        $expenseStatusLabels = [
            'draft' => 'مسودة',
            'pending' => 'قيد المراجعة',
            'approved' => 'معتمد',
            'paid' => 'مدفوع',
            'rejected' => 'مرفوض',
        ];
        $taskStatusLabels = [
            'new' => 'جديدة',
            'in_progress' => 'قيد التنفيذ',
            'pending_review' => 'بانتظار المراجعة',
            'completed' => 'مكتملة',
            'overdue' => 'متأخرة',
        ];
        $tabs = [
            'overview' => 'نظرة عامة',
            'tasks' => 'المهام',
            'files' => 'الملفات',
            'finance' => 'المالية',
            'updates' => 'التحديثات',
        ];
    @endphp

    <div class="ds-page-toolbar">
        <div>
            <a href="{{ route('projects.index') }}" class="ds-link"><i class="fas fa-arrow-right"></i> العودة للمشاريع</a>
            <h1 class="ds-page-title">{{ $project->name }}</h1>
            <p class="ds-text-muted">{{ $statusLabels[$project->status] ?? $project->status }}</p>
        </div>
    </div>

    <nav class="ds-tabs" aria-label="تبويبات المشروع">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                class="ds-tab {{ $activeTab === $key ? 'ds-tab-active' : '' }}"
                wire:click="setTab('{{ $key }}')"
            >
                {{ $label }}
            </button>
        @endforeach
    </nav>

    @if ($activeTab === 'overview')
        <section class="ds-section-spaced">
            <div class="ds-stats-grid">
                <div class="ds-stat-card">
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">المدير</span>
                        <span class="ds-stat-mini-val">{{ $project->manager?->name ?? '—' }}</span>
                    </div>
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">الميزانية</span>
                        <span class="ds-stat-mini-val">{{ $project->budget !== null ? number_format((float) $project->budget, 2) : '—' }}</span>
                    </div>
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">تاريخ البداية</span>
                        <span class="ds-stat-mini-val">{{ $project->start_date?->format('Y-m-d') ?? '—' }}</span>
                    </div>
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">تاريخ النهاية</span>
                        <span class="ds-stat-mini-val">{{ $project->end_date?->format('Y-m-d') ?? '—' }}</span>
                    </div>
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">المرحلة الحالية</span>
                        <span class="ds-stat-mini-val">{{ $project->current_phase ?: '—' }}</span>
                    </div>
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">نسبة الإنجاز</span>
                        <span class="ds-stat-mini-val">{{ $completionPercent }}%</span>
                    </div>
                </div>
            </div>

            @if ($project->idea_goal)
                <div class="ds-card ds-section-spaced">
                    <h3 class="ds-section-heading">فكرة / هدف المشروع</h3>
                    <p>{{ $project->idea_goal }}</p>
                </div>
            @endif

            @if ($project->team->isNotEmpty())
                <div class="ds-card">
                    <h3 class="ds-section-heading">فريق المشروع</h3>
                    <p>{{ $project->team->pluck('name')->join('، ') }}</p>
                </div>
            @endif
        </section>
    @endif

    @if ($activeTab === 'tasks')
        <section class="ds-section-spaced">
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>العنوان</th>
                        <th>المكلف</th>
                        <th>الحالة</th>
                        <th>تاريخ الاستحقاق</th>
                    </tr>
                </x-slot:head>
                @forelse ($tasks as $task)
                    <tr wire:key="task-{{ $task->id }}">
                        <td>{{ $task->title }}</td>
                        <td>{{ $task->assignee?->name ?? '—' }}</td>
                        <td>{{ $taskStatusLabels[$task->status] ?? $task->status }}</td>
                        <td>{{ $task->due_date?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="ds-text-muted ds-table-empty">لا توجد مهام لهذا المشروع</td>
                    </tr>
                @endforelse
            </x-ds-table>
        </section>
    @endif

    @if ($activeTab === 'files')
        <section class="ds-section-spaced">
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>العنوان</th>
                        <th>التصنيف</th>
                        <th>رفع بواسطة</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($documents as $document)
                    <tr wire:key="doc-{{ $document->id }}">
                        <td>{{ $document->title }}</td>
                        <td>{{ $document->category }}</td>
                        <td>{{ $document->uploader?->name ?? '—' }}</td>
                        <td>{{ $document->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            @can('download', $document)
                                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.files.download', $document) }}" title="تحميل">
                                    <i class="fas fa-download"></i>
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="ds-text-muted ds-table-empty">لا توجد مستندات لهذا المشروع</td>
                    </tr>
                @endforelse
            </x-ds-table>
        </section>
    @endif

    @if ($activeTab === 'finance')
        <section class="ds-section-spaced">
            <div class="ds-stats-grid">
                <div class="ds-stat-card">
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">الميزانية</span>
                        <span class="ds-stat-mini-val">{{ $project->budget !== null ? number_format((float) $project->budget, 2) : '—' }}</span>
                    </div>
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">الإنفاق الفعلي</span>
                        <span class="ds-stat-mini-val">{{ number_format($actualSpend, 2) }}</span>
                    </div>
                    <div class="ds-stat-mini">
                        <span class="ds-stat-mini-label">المتبقي</span>
                        <span class="ds-stat-mini-val">{{ $remaining !== null ? number_format($remaining, 2) : '—' }}</span>
                    </div>
                </div>
            </div>

            <h3 class="ds-section-heading">طلبات الصرف</h3>
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>النوع</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>مقدم الطلب</th>
                        <th>السبب</th>
                    </tr>
                </x-slot:head>
                @forelse ($expenses as $expense)
                    <tr wire:key="expense-{{ $expense->id }}">
                        <td>{{ $expense->type }}</td>
                        <td>{{ number_format((float) $expense->amount, 2) }}</td>
                        <td>{{ $expenseStatusLabels[$expense->status] ?? $expense->status }}</td>
                        <td>{{ $expense->requester?->name ?? '—' }}</td>
                        <td>{{ Str::limit($expense->reason, 60) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="ds-text-muted ds-table-empty">لا توجد طلبات صرف لهذا المشروع</td>
                    </tr>
                @endforelse
            </x-ds-table>
        </section>
    @endif

    @if ($activeTab === 'updates')
        <section class="ds-section-spaced">
            @can('submitUpdate', $project)
                <div class="ds-card ds-section-spaced">
                    <h3 class="ds-section-heading">تحديث أسبوعي جديد</h3>
                    <div class="ds-grid-2">
                        <x-ds-form-group label="تاريخ التحديث" :error="$errors->first('update_date')">
                            <input type="date" class="ds-input" wire:model="update_date">
                        </x-ds-form-group>
                    </div>
                    <x-ds-form-group label="ما تم إنجازه" :error="$errors->first('done')">
                        <textarea class="ds-input" rows="3" wire:model="done"></textarea>
                    </x-ds-form-group>
                    <x-ds-form-group label="ما سيتم العمل عليه" :error="$errors->first('next')">
                        <textarea class="ds-input" rows="3" wire:model="next"></textarea>
                    </x-ds-form-group>
                    <x-ds-form-group label="المعوقات" :error="$errors->first('blockers')">
                        <textarea class="ds-input" rows="2" wire:model="blockers"></textarea>
                    </x-ds-form-group>
                    <x-ds-form-group label="قرار مطلوب" :error="$errors->first('decision_needed')">
                        <textarea class="ds-input" rows="2" wire:model="decision_needed"></textarea>
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="submitWeeklyUpdate">
                        <i class="fas fa-save"></i> حفظ التحديث
                    </button>
                </div>
            @endcan

            <h3 class="ds-section-heading">سجل التحديثات</h3>
            @forelse ($updates as $update)
                <div class="ds-card ds-section-spaced" wire:key="update-{{ $update->id }}">
                    <div class="ds-page-toolbar">
                        <strong>{{ $update->date->format('Y-m-d') }}</strong>
                        <span class="ds-text-muted">{{ $update->author?->name ?? '—' }}</span>
                    </div>
                    <p><strong>تم:</strong> {{ $update->done }}</p>
                    <p><strong>التالي:</strong> {{ $update->next }}</p>
                    @if ($update->blockers)
                        <p><strong>المعوقات:</strong> {{ $update->blockers }}</p>
                    @endif
                    @if ($update->decision_needed)
                        <p><strong>قرار مطلوب:</strong> {{ $update->decision_needed }}</p>
                    @endif
                </div>
            @empty
                <p class="ds-text-muted">لا توجد تحديثات بعد</p>
            @endforelse
        </section>
    @endif
</div>
