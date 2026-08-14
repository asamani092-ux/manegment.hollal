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
                $tasks = $offboardingTasks[$user->id] ?? collect();
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
                        <span class="ds-ltr-num">{{ (int) $counts->done }} / {{ (int) $counts->total }}</span>
                        <ul style="margin:.35rem 0 0;padding-inline-start:1rem;font-size:.85rem">
                            @foreach ($tasks as $task)
                                <li>
                                    <a class="ds-link" href="{{ route('tasks.index', ['open' => $task->id]) }}">{{ $task->title }}</a>
                                    — {{ $task->status }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($rowHolds === [])
                        <span class="ds-text-muted">لا يوجد</span>
                    @else
                        <span class="ds-badge ds-badge-warning">{{ implode('، ', $rowHolds) }}</span>
                    @endif
                </td>
                <td>
                    @if ($user->id !== auth()->id())
                        @if (($user->employment_status ?? '') === \App\Models\User::STATUS_FROZEN)
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="unfreezeAccount({{ $user->id }})">
                                إلغاء التجميد
                            </button>
                        @elseif (! $user->offboarding_started_at)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askStartOffboarding({{ $user->id }})">
                                بدء إنهاء العلاقة
                            </button>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="freezeAccount({{ $user->id }})" wire:confirm="تجميد الحساب مؤقتًا ومنع الدخول؟">
                                تعطيل مؤقت (تجميد)
                            </button>
                        @else
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm"
                                    wire:click="askCompleteOffboarding({{ $user->id }})"
                                    @disabled($rowHolds !== [])>
                                إغلاق وتعطيل الحساب
                            </button>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="cancelOffboarding({{ $user->id }})" wire:confirm="التراجع عن بدء الإنهاء وحذف المهام غير المكتملة؟">
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
        <p>سيُنشأ 4 مهام تسليم في إسناد يمكن فتحها ومتابعتها من عمود المهام. الحساب يبقى نشطًا حتى خطوة الإغلاق النهائية.</p>
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
</x-ds-page>
