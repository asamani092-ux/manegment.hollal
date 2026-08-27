<x-ds-page>
    <x-ds-page-header title="التهيئة وإنهاء العلاقة" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        <strong>إنهاء العلاقة:</strong> إنشاء مهام تسليم في إسناد ← متابعتها حتى الاكتمال ← تعطيل الحساب كآخر خطوة.
        <strong>التجميد:</strong> تعطيل مؤقت للدخول دون إنهاء العلاقة (قابل للعكس).
        إذا كان تاريخ العقد لم ينتهِ بعد، يجب إرفاق <strong>مخالصة</strong> من تبويب المستندات في الملف الوظيفي قبل الإغلاق.
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

    {{-- Confirm dialogs (inline overlay — reliable with Livewire morph) --}}
    @if ($confirmStartId)
        <div class="ds-modal-overlay" wire:key="confirm-start" wire:click.self="cancelConfirm" wire:keydown.escape.window="cancelConfirm">
            <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl">
                <div class="ds-modal-header">
                    <h3>تأكيد بدء إنهاء العلاقة</h3>
                    <button type="button" class="ds-modal-close" wire:click="cancelConfirm" aria-label="إغلاق">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <p>سيُنشأ 4 مهام تسليم في إسناد. الحساب يبقى نشطًا حتى خطوة الإغلاق النهائية.</p>
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

    {{-- Unified floating panel: tasks + holds --}}
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
                    <h3>متابعة إنهاء العلاقة — {{ $detailUser->name }}</h3>
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
                                        <strong>{{ $task->title }}</strong>
                                        <x-ds-status-badge :status="$statusAr[$task->status] ?? $task->status" />
                                    </div>
                                    <a class="ds-link" href="{{ route('tasks.index', ['open' => $task->id]) }}">فتح في إسناد</a>
                                </li>
                            @empty
                                <li class="ds-text-muted">لا مهام إنهاء لهذا الموظف</li>
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
