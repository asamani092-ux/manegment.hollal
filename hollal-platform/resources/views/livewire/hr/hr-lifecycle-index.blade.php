<x-ds-page>
    <x-ds-page-header title="التهيئة وإنهاء العلاقة" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        <strong>التهيئة:</strong> عند إضافة موظف يُختار مسؤول واحد لكل مهام التهيئة الأربع.
        <strong>إنهاء العلاقة:</strong> اختيار مسؤول واحد لكل مهام الإنهاء ← متابعتها (حالة/مرفق/حذف) ← تعطيل الحساب كآخر خطوة.
        <strong>التجميد:</strong> تعطيل مؤقت للدخول دون إنهاء العلاقة.
    </p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الموظف</th>
                <th scope="col">الجوال</th>
                <th scope="col">الحالة الوظيفية</th>
                <th scope="col">المتابعة</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($users as $user)
            @php
                $uid = (int) $user->id;
                $rowHolds = $holds[$uid] ?? [];
                $counts = $taskCounts[$uid] ?? null;
                $hasTasks = $counts && (int) $counts->total > 0;
                $hasHolds = $rowHolds !== [];
            @endphp
            <tr wire:key="life-{{ $uid }}">
                <td>{{ $user->name }}</td>
                <td class="ds-ltr-num">{{ $user->phone }}</td>
                <td>
                    <x-ds-status-badge :status="$user->employment_status ?? 'نشط'" />
                    @if ($user->offboarding_started_at)
                        <span class="ds-badge ds-badge-warning">قيد الإنهاء</span>
                    @endif
                </td>
                <td>
                    @if (! $hasTasks && ! $hasHolds)
                        <span class="ds-text-muted">—</span>
                    @else
                        <div class="ds-toolbar-actions" style="flex-wrap:wrap;gap:.35rem">
                            @if ($hasTasks)
                                <button
                                    type="button"
                                    class="ds-btn ds-btn-outline ds-btn-sm"
                                    wire:click="openDetails({{ $uid }}, 'tasks')"
                                    wire:key="btn-tasks-{{ $uid }}"
                                >
                                    المهام
                                    <span class="ds-ltr-num">({{ (int) $counts->done }}/{{ (int) $counts->total }})</span>
                                </button>
                            @endif
                            @if ($hasHolds)
                                <button
                                    type="button"
                                    class="ds-btn ds-btn-outline ds-btn-sm"
                                    wire:click="openDetails({{ $uid }}, 'holds')"
                                    wire:key="btn-holds-{{ $uid }}"
                                >
                                    الموانع
                                    <span class="ds-ltr-num">({{ count($rowHolds) }})</span>
                                </button>
                            @endif
                        </div>
                    @endif
                </td>
                <td>
                    @if ($user->id !== auth()->id())
                        @if (($user->employment_status ?? '') === \App\Models\User::STATUS_FROZEN)
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="askUnfreeze({{ $uid }})">
                                إلغاء التجميد
                            </button>
                        @elseif (! $user->offboarding_started_at)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askStartOffboarding({{ $uid }})">
                                بدء إنهاء العلاقة
                            </button>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askFreeze({{ $uid }})">
                                تعطيل مؤقت (تجميد)
                            </button>
                        @else
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm"
                                    wire:click="askCompleteOffboarding({{ $uid }})"
                                    @disabled($hasHolds)>
                                إغلاق وتعطيل الحساب
                            </button>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askCancelOffboarding({{ $uid }})">
                                تراجع عن الإنهاء
                            </button>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ds-empty-state message="لا يوجد عاملون" icon="fa-user-gear" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $users->links() }}

    @if ($confirmStartId)
        <div class="ds-modal-overlay" wire:key="confirm-start" wire:click.self="cancelConfirm" wire:keydown.escape.window="cancelConfirm">
            <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تأكيد بدء إنهاء العلاقة</h3>
                    <button type="button" class="ds-modal-close" wire:click="cancelConfirm" aria-label="إغلاق">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <p>سيُنشأ 4 مهام تسليم في إسناد لمسؤول واحد لكل المهام.</p>
                    <x-ds-form-group label="مسؤول المهام الأربع" :error="$errors->first('checklistAssigneeId')">
                        <select class="ds-input" wire:model="checklistAssigneeId">
                            <option value="">— اختر —</option>
                            @foreach ($assigneeOptions as $opt)
                                <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <div class="ds-toolbar-actions">
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="startOffboarding({{ $confirmStartId }})">تأكيد البدء</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmCompleteId)
        <div class="ds-modal-overlay" wire:key="confirm-complete" wire:click.self="cancelConfirm" wire:keydown.escape.window="cancelConfirm">
            <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تأكيد إغلاق وتعطيل الحساب</h3>
                    <button type="button" class="ds-modal-close" wire:click="cancelConfirm" aria-label="إغلاق">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <p>تعطيل الحساب خطوة أخيرة بعد اكتمال المهام وخلو الموانع. لا يمكن التراجع بعد الإغلاق.</p>
                    <div class="ds-toolbar-actions">
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="completeOffboarding({{ $confirmCompleteId }})">تأكيد الإغلاق</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmFreezeId)
        <div class="ds-modal-overlay" wire:key="confirm-freeze" wire:click.self="cancelConfirm" wire:keydown.escape.window="cancelConfirm">
            <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تأكيد التجميد المؤقت</h3>
                    <button type="button" class="ds-modal-close" wire:click="cancelConfirm" aria-label="إغلاق">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <p>سيُمنع دخول الحساب مؤقتًا دون إنهاء العلاقة. يمكن إلغاء التجميد لاحقًا.</p>
                    <div class="ds-toolbar-actions">
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="freezeAccount({{ $confirmFreezeId }})">تأكيد التجميد</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmUnfreezeId)
        <div class="ds-modal-overlay" wire:key="confirm-unfreeze" wire:click.self="cancelConfirm" wire:keydown.escape.window="cancelConfirm">
            <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تأكيد إلغاء التجميد</h3>
                    <button type="button" class="ds-modal-close" wire:click="cancelConfirm" aria-label="إغلاق">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <p>سيُعاد تفعيل الحساب ويُسمح بالدخول مجددًا.</p>
                    <div class="ds-toolbar-actions">
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="unfreezeAccount({{ $confirmUnfreezeId }})">تأكيد إلغاء التجميد</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmCancelOffboardingId)
        <div class="ds-modal-overlay" wire:key="confirm-cancel-off" wire:click.self="cancelConfirm" wire:keydown.escape.window="cancelConfirm">
            <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تأكيد التراجع عن الإنهاء</h3>
                    <button type="button" class="ds-modal-close" wire:click="cancelConfirm" aria-label="إغلاق">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <p>سيُلغى بدء إنهاء العلاقة وتُحذف المهام غير المكتملة المرتبطة به.</p>
                    <div class="ds-toolbar-actions">
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="cancelOffboarding({{ $confirmCancelOffboardingId }})">تأكيد التراجع</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($detailUserId && $detailUser)
        @php
            $statusAr = [
                'new' => 'جديدة',
                'in_progress' => 'قيد التنفيذ',
                'pending_review' => 'بانتظار المراجعة',
                'waiting_review' => 'بانتظار المراجعة',
                'completed' => 'مكتملة',
                'cancelled' => 'ملغاة',
            ];
        @endphp
        <div
            class="ds-modal-overlay"
            wire:key="detail-panel-{{ $detailUserId }}"
            wire:click.self="closeDetails"
            wire:keydown.escape.window="closeDetails"
            style="z-index:1300"
        >
            <div class="ds-modal ds-modal-lg" role="dialog" aria-modal="true" dir="rtl" wire:click.stop>
                <div class="ds-modal-header">
                    <h3>متابعة التهيئة/الإنهاء — {{ $detailUser->name }}</h3>
                    <button type="button" class="ds-modal-close" wire:click="closeDetails" aria-label="إغلاق">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-filter-bar ds-mb-3" style="display:flex;gap:.5rem;flex-wrap:wrap">
                        <button
                            type="button"
                            class="ds-btn ds-btn-sm {{ $detailTab === 'tasks' ? 'ds-btn-primary' : 'ds-btn-outline' }}"
                            wire:click="setDetailTab('tasks')"
                        >
                            المهام
                            <span class="ds-ltr-num">({{ $detailTasks->count() }})</span>
                        </button>
                        <button
                            type="button"
                            class="ds-btn ds-btn-sm {{ $detailTab === 'holds' ? 'ds-btn-primary' : 'ds-btn-outline' }}"
                            wire:click="setDetailTab('holds')"
                        >
                            الموانع
                            <span class="ds-ltr-num">({{ count($detailHolds) }})</span>
                        </button>
                    </div>

                    @if ($detailTab === 'tasks')
                        <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.75rem">
                            @forelse ($detailTasks as $task)
                                <li class="ds-card" style="padding:.75rem 1rem" wire:key="dtask-{{ $task->id }}">
                                    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap">
                                        <div>
                                            <strong>{{ $task->title }}</strong>
                                            <div class="ds-text-muted" style="font-size:.85rem">
                                                {{ $task->role_label }}
                                                @if ($task->assignee)
                                                    — المسؤول: {{ $task->assignee->name }}
                                                @endif
                                            </div>
                                        </div>
                                        <x-ds-status-badge :status="$statusAr[$task->status] ?? $task->status" />
                                    </div>

                                    @if ($taskStatusId === $task->id)
                                        <div class="ds-mt-3">
                                            <x-ds-form-group label="الحالة">
                                                <select class="ds-input" wire:model="taskStatus">
                                                    <option value="new">جديدة</option>
                                                    <option value="in_progress">قيد التنفيذ</option>
                                                    <option value="pending_review">بانتظار المراجعة</option>
                                                    <option value="completed">مكتملة</option>
                                                    <option value="cancelled">ملغاة</option>
                                                </select>
                                            </x-ds-form-group>
                                            <div class="ds-toolbar-actions">
                                                <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="saveTaskStatus">حفظ الحالة</button>
                                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="$set('taskStatusId', null)">إلغاء</button>
                                            </div>
                                        </div>
                                    @elseif ($taskAttachId === $task->id)
                                        <div class="ds-mt-3">
                                            <x-ds-form-group label="مرفق" :error="$errors->first('taskAttachment')">
                                                <input type="file" class="ds-input" wire:model="taskAttachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </x-ds-form-group>
                                            <div class="ds-toolbar-actions">
                                                <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="saveTaskAttachment">رفع</button>
                                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="$set('taskAttachId', null)">إلغاء</button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="ds-toolbar-actions ds-mt-3" style="flex-wrap:wrap">
                                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="beginTaskStatus({{ $task->id }})">تغيير الحالة</button>
                                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="beginTaskAttach({{ $task->id }})">مرفق</button>
                                            @if ($task->attachment_path)
                                                <a class="ds-link" href="{{ route('tasks.files.download', ['task' => $task->id, 'type' => 'attachment']) }}">تنزيل المرفق</a>
                                            @endif
                                            @if ($task->status !== 'completed')
                                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deleteLifecycleTask({{ $task->id }})" wire:confirm="حذف هذه المهمة؟">حذف</button>
                                            @endif
                                            <a class="ds-link" href="{{ route('tasks.index', ['open' => $task->id]) }}">فتح في إسناد</a>
                                        </div>
                                    @endif
                                </li>
                            @empty
                                <li class="ds-text-muted">لا مهام تهيئة/إنهاء لهذا الموظف</li>
                            @endforelse
                        </ul>
                    @else
                        <p class="ds-text-muted ds-mb-3">يجب معالجة الموانع قبل إغلاق إنهاء العلاقة وتعطيل الحساب.</p>
                        <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.75rem">
                            @forelse ($detailHolds as $hold)
                                <li class="ds-card" style="padding:.75rem 1rem;display:flex;align-items:flex-start;gap:.75rem" wire:key="dhold-{{ $loop->index }}">
                                    <span class="ds-badge ds-badge-warning" aria-hidden="true">!</span>
                                    <span>
                                        {{ $hold }}
                                        @if (str_contains($hold, 'أصول'))
                                            <a class="ds-link" href="{{ route('assets.index', ['search' => $detailUser->name, 'statusTab' => 'all']) }}">فتح في الأصول</a>
                                        @elseif (str_contains($hold, 'عهد مالية'))
                                            <a class="ds-link" href="{{ route('custodies.index', ['search' => $detailUser->name]) }}">فتح في العهد</a>
                                        @endif
                                    </span>
                                </li>
                            @empty
                                <li class="ds-text-muted">لا موانع حالياً</li>
                            @endforelse
                        </ul>
                    @endif

                    <div class="ds-toolbar-actions ds-mt-3">
                        <button type="button" class="ds-btn ds-btn-outline" wire:click="closeDetails">إغلاق</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
