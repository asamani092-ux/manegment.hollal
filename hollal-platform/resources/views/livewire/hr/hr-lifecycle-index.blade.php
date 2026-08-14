<x-ds-page>
    <x-ds-page-header title="التهيئة وإنهاء العلاقة" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        <strong>إنهاء العلاقة:</strong> إنشاء مهام تسليم في إسناد ← متابعتها حتى الاكتمال ← تعطيل الحساب كآخر خطوة.
        <strong>التجميد:</strong> تعطيل مؤقت للدخول دون إنهاء العلاقة (قابل للعكس).
    </p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الموظف</th>
                <th scope="col">الجوال</th>
                <th scope="col">الحالة الوظيفية</th>
                <th scope="col">مهام الإنهاء</th>
                <th scope="col">موانع الإغلاق</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($users as $user)
            @php
                $rowHolds = $holds[$user->id] ?? [];
                $counts = $taskCounts[$user->id] ?? null;
            @endphp
            <tr wire:key="life-{{ $user->id }}">
                <td>{{ $user->name }}</td>
                <td class="ds-ltr-num">{{ $user->phone }}</td>
                <td>
                    <x-ds-status-badge :status="$user->employment_status ?? 'نشط'" />
                    @if ($user->offboarding_started_at)
                        <span class="ds-badge ds-badge-warning">قيد الإنهاء</span>
                    @endif
                </td>
                <td>
                    @if ($counts)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openTasksDrawer({{ $user->id }})">
                            عرض المهام
                            <span class="ds-ltr-num">({{ (int) $counts->done }}/{{ (int) $counts->total }})</span>
                        </button>
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($rowHolds === [])
                        <span class="ds-text-muted">لا يوجد</span>
                    @else
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openHoldsDrawer({{ $user->id }})">
                            عرض الموانع
                            <span class="ds-badge ds-badge-warning ds-ltr-num">{{ count($rowHolds) }}</span>
                        </button>
                    @endif
                </td>
                <td>
                    @if ($user->id !== auth()->id())
                        @if (($user->employment_status ?? '') === \App\Models\User::STATUS_FROZEN)
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="askUnfreeze({{ $user->id }})">
                                إلغاء التجميد
                            </button>
                        @elseif (! $user->offboarding_started_at)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askStartOffboarding({{ $user->id }})">
                                بدء إنهاء العلاقة
                            </button>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askFreeze({{ $user->id }})">
                                تعطيل مؤقت (تجميد)
                            </button>
                        @else
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm"
                                    wire:click="askCompleteOffboarding({{ $user->id }})"
                                    @disabled($rowHolds !== [])>
                                إغلاق وتعطيل الحساب
                            </button>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askCancelOffboarding({{ $user->id }})">
                                تراجع عن الإنهاء
                            </button>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ds-empty-state message="لا يوجد عاملون" icon="fa-user-gear" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $users->links() }}

    <x-ds-modal :show="$confirmStartId !== null" title="تأكيد بدء إنهاء العلاقة" close-action="cancelConfirm">
        <p>سيُنشأ 4 مهام تسليم في إسناد يمكن فتحها ومتابعتها من نافذة المهام. الحساب يبقى نشطًا حتى خطوة الإغلاق النهائية.</p>
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="startOffboarding({{ $confirmStartId }})">تأكيد البدء</button>
        </div>
    </x-ds-modal>

    <x-ds-modal :show="$confirmCompleteId !== null" title="تأكيد إغلاق وتعطيل الحساب" close-action="cancelConfirm">
        <p>تعطيل الحساب خطوة أخيرة بعد اكتمال المهام وخلو الموانع. لا يمكن التراجع بعد الإغلاق.</p>
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="completeOffboarding({{ $confirmCompleteId }})">تأكيد الإغلاق</button>
        </div>
    </x-ds-modal>

    <x-ds-modal :show="$confirmFreezeId !== null" title="تأكيد التجميد المؤقت" close-action="cancelConfirm">
        <p>سيُمنع دخول الحساب مؤقتًا دون إنهاء العلاقة. يمكن إلغاء التجميد لاحقًا.</p>
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="freezeAccount({{ $confirmFreezeId }})">تأكيد التجميد</button>
        </div>
    </x-ds-modal>

    <x-ds-modal :show="$confirmUnfreezeId !== null" title="تأكيد إلغاء التجميد" close-action="cancelConfirm">
        <p>سيُعاد تفعيل الحساب ويُسمح بالدخول مجددًا.</p>
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="unfreezeAccount({{ $confirmUnfreezeId }})">تأكيد إلغاء التجميد</button>
        </div>
    </x-ds-modal>

    <x-ds-modal :show="$confirmCancelOffboardingId !== null" title="تأكيد التراجع عن الإنهاء" close-action="cancelConfirm">
        <p>سيُلغى بدء إنهاء العلاقة وتُحذف المهام غير المكتملة المرتبطة به.</p>
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelConfirm">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="cancelOffboarding({{ $confirmCancelOffboardingId }})">تأكيد التراجع</button>
        </div>
    </x-ds-modal>

    @php
        $drawerTasks = $tasksDrawerUserId
            ? ($offboardingTasks[$tasksDrawerUserId] ?? collect())
            : collect();
        $drawerUser = $tasksDrawerUserId
            ? $users->firstWhere('id', $tasksDrawerUserId)
            : null;
        $statusAr = [
            'new' => 'جديدة',
            'in_progress' => 'قيد التنفيذ',
            'pending_review' => 'بانتظار المراجعة',
            'waiting_review' => 'بانتظار المراجعة',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغاة',
        ];
    @endphp
    <x-ds-modal :show="$tasksDrawerUserId !== null" title="مهام إنهاء العلاقة — {{ $drawerUser?->name ?? '' }}" close-action="closeTasksDrawer" size="lg">
        <ul class="ds-list" style="list-style:none;padding:0;margin:0;display:grid;gap:.75rem">
            @forelse ($drawerTasks as $task)
                <li class="ds-card" style="padding:.75rem 1rem">
                    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap">
                        <strong>{{ $task->title }}</strong>
                        <x-ds-status-badge :status="$statusAr[$task->status] ?? $task->status" />
                    </div>
                    <a class="ds-link" href="{{ route('tasks.index', ['open' => $task->id]) }}">فتح في إسناد</a>
                </li>
            @empty
                <li class="ds-text-muted">لا مهام</li>
            @endforelse
        </ul>
        <div class="ds-toolbar-actions ds-mt-3">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="closeTasksDrawer">إغلاق</button>
        </div>
    </x-ds-modal>

    @php
        $holdsDrawerItems = $holdsDrawerUserId
            ? ($holds[$holdsDrawerUserId] ?? [])
            : [];
        $holdsDrawerUser = $holdsDrawerUserId
            ? $users->firstWhere('id', $holdsDrawerUserId)
            : null;
    @endphp
    <x-ds-modal :show="$holdsDrawerUserId !== null" title="موانع الإغلاق — {{ $holdsDrawerUser?->name ?? '' }}" close-action="closeHoldsDrawer">
        <p class="ds-text-muted ds-mb-3">يجب معالجة هذه الموانع قبل إغلاق إنهاء العلاقة وتعطيل الحساب.</p>
        <ul class="ds-list" style="list-style:none;padding:0;margin:0;display:grid;gap:.75rem">
            @forelse ($holdsDrawerItems as $hold)
                <li class="ds-card" style="padding:.75rem 1rem;display:flex;align-items:flex-start;gap:.75rem">
                    <span class="ds-badge ds-badge-warning" aria-hidden="true">!</span>
                    <span>{{ $hold }}</span>
                </li>
            @empty
                <li class="ds-text-muted">لا موانع</li>
            @endforelse
        </ul>
        <div class="ds-toolbar-actions ds-mt-3">
            <button type="button" class="ds-btn ds-btn-outline" wire:click="closeHoldsDrawer">إغلاق</button>
        </div>
    </x-ds-modal>
</x-ds-page>
