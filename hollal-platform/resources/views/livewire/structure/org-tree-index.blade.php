<x-ds-page>
    <x-ds-page-header title="الهيكل التنظيمي" />

    <section class="ds-section ds-filter-bar">
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'tree')">الشجرة</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'table')">عرض الجدول</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'jobs')">الوظائف</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'transfers')">النقل</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('tab', 'committees')">اللجان</button>
        @can('structure.manage')
            <button type="button" class="ds-btn ds-btn-primary" wire:click="openUnitModal">إضافة إدارة</button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="openTopModal">إضافة منصب أعلى</button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="gatherUnderTop" wire:confirm="ستُربط الإدارات غير التابعة بمنصب بآخر منصب أعلى. متابعة؟">اربط الإدارات بآخر منصب أعلى</button>
        @endcan
    </section>

    @if ($tab === 'tree')
        <section class="ds-section org-print-root" id="org-chart-root">
            <div class="org-toolbar">
                <input id="org-search" class="ds-input" placeholder="بحث" autocomplete="off">
                <button type="button" class="ds-btn ds-btn-sm" data-org-zoom="in">تكبير</button>
                <button type="button" class="ds-btn ds-btn-sm" data-org-zoom="out">تصغير</button>
                <button type="button" class="ds-btn ds-btn-sm" data-org-zoom="fit">ملاءمة الشاشة</button>
                <button type="button" class="ds-btn ds-btn-sm" data-org-fold="all">طي الكل</button>
                <button type="button" class="ds-btn ds-btn-sm" data-org-fold="none">فتح الكل</button>
                <button type="button" class="ds-btn ds-btn-sm" onclick="window.print()">طباعة / PDF</button>
            </div>
            <div class="org-chart-scroll">
                <div class="org-chart-stage" id="org-chart-stage">
                    <ul class="org-chart">
                        @forelse ($chart['roots'] as $node)
                            @include('livewire.structure.partials.org-chart-node', ['node' => $node])
                        @empty
                            @if (($chart['unlinked'] ?? []) === [])
                                <li class="org-empty">لا يوجد هيكل بعد. أضف منصبًا أعلى أو إدارة.</li>
                            @endif
                        @endforelse
                    </ul>
                </div>
            </div>
            @if (($chart['unlinked'] ?? []) !== [])
                <section class="org-unlinked">
                    <h2>إدارات غير مرتبطة</h2>
                    <p>هذه الإدارات ليست تحت منصب أعلى. اربط كل واحدة حتى تدخل الشجرة.</p>
                    @foreach ($chart['unlinked'] as $admin)
                        <div class="org-unlinked-row">
                            <strong>{{ $admin['title'] }}</strong>
                            @can('structure.manage')
                                <select class="ds-input" wire:model="linkChoice.{{ $admin['id'] }}">
                                    <option value="">اربطها بـ</option>
                                    @foreach ($topPositions as $seat)
                                        <option value="{{ $seat->id }}">{{ $seat->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="linkAdministration({{ $admin['id'] }})">حفظ</button>
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askDeleteUnit({{ $admin['id'] }})">حذف</button>
                            @endcan
                        </div>
                    @endforeach
                </section>
            @endif
        </section>
    @endif
    @if ($drawer)
        @teleport('body')
            <div class="org-drawer-overlay" wire:click.self="closeDrawer" wire:keydown.escape.window="closeDrawer">
                <aside class="org-drawer" role="dialog" aria-modal="true" aria-label="{{ $drawer->name }}">
                    <h2>{{ $drawer->name }}</h2>
                    <p>الغرض: {{ $drawer->job_purpose ?: '—' }}</p>
                    <p>المسؤول: {{ $drawer->manager?->name ?: '—' }}</p>
                    @if ($drawer->job_responsibilities)
                        <ul>
                            @foreach ($drawer->job_responsibilities as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <p>الأعضاء:</p>
                    <ul>
                        @forelse ($drawer->members as $member)
                            <li><a href="{{ route('users.profile', $member->id) }}">{{ $member->name }}</a></li>
                        @empty
                            <li>لا يوجد شاغل</li>
                        @endforelse
                    </ul>
                    @can('structure.manage')
                        @if ($drawer->level === \App\Models\OrgUnit::LEVEL_ADMINISTRATION)
                            <label>يتبع لـ
                                <select class="ds-input" wire:change="followTop({{ $drawer->id }}, $event.target.value)">
                                    @foreach ($topPositions as $seat)
                                        <option value="{{ $seat->id }}" @selected((int) $drawer->parent_id === (int) $seat->id)>{{ $seat->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="openUnitModal({{ $drawer->id }})">إضافة فرع</button>
                        @if ($drawer->level === \App\Models\OrgUnit::LEVEL_ADMINISTRATION)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askDeleteUnit({{ $drawer->id }})">حذف</button>
                        @endif
                    @endcan
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="closeDrawer">إغلاق</button>
                </aside>
            </div>
        @endteleport
    @endif
    @if ($unitDeleteTarget)
        @teleport('body')
            <div class="ds-modal-overlay" wire:key="org-unit-delete-{{ $unitDeleteTarget->id }}" wire:click.self="cancelDeleteUnit" wire:keydown.escape.window="cancelDeleteUnit" style="z-index:1300">
                <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl" wire:click.stop>
                    <div class="ds-modal-header">
                        <h3>تأكيد حذف الإدارة</h3>
                        <button type="button" class="ds-modal-close" wire:click="cancelDeleteUnit" aria-label="إغلاق">&times;</button>
                    </div>
                    <div class="ds-modal-body">
                        <p>حذف الإدارة «{{ $unitDeleteTarget->name }}»؟ تُخفى الأقسام والوظائف التابعة، ويبقى سجل نقل الموظفين.</p>
                        <div class="ds-toolbar-actions">
                            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelDeleteUnit">إلغاء</button>
                            <button type="button" class="ds-btn ds-btn-primary" wire:click="deleteUnit({{ $unitDeleteTarget->id }})">تأكيد الحذف</button>
                        </div>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    @if ($tab === 'table')
        <section class="ds-section">
            <x-ds-table>
                <x-slot:head>
                    <tr><th>الوحدة</th><th>المستوى</th><th>المسؤول</th><th>الأعضاء</th><th>إجراءات</th></tr>
                </x-slot:head>
                @forelse ($tree as $root)
                    @include('livewire.structure.partials.org-node', [
                        'node' => $root,
                        'depth' => 0,
                        'adminColor' => $adminColors[$root->id] ?? '#1B6B93',
                        'adminColors' => $adminColors,
                    ])
                @empty
                    <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا يوجد هيكل بعد</td></tr>
                @endforelse
            </x-ds-table>
        </section>

        @if ($jobCard)
            <section class="ds-section">
                <h2 class="ds-section-title">بطاقة الوظيفة — {{ $jobCard->name }}</h2>
                <p>الغرض: {{ $jobCard->job_purpose ?? '—' }}</p>
                <p>المسؤوليات:</p>
                <ul>
                    @foreach ($jobCard->job_responsibilities ?? [] as $responsibility)
                        <li>{{ $responsibility }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif

    @if ($tab === 'jobs')
        <section class="ds-section">
            <x-ds-table>
                <x-slot:head>
                    <tr><th>الوظيفة</th><th>التابع لـ</th><th>المسؤول</th><th>الغرض</th><th>إجراءات</th></tr>
                </x-slot:head>
                @forelse ($jobs as $job)
                    <tr wire:key="job-{{ $job->id }}">
                        <td>{{ $job->name }}</td>
                        <td>{{ $job->parent?->name ?? '—' }}</td>
                        <td>{{ $job->manager?->name ?? '—' }}</td>
                        <td>{{ $job->job_purpose ?? '—' }}</td>
                        <td>
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="viewJobCard({{ $job->id }})">بطاقة الوظيفة</button>
                            @can('structure.positions.manage')
                                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.jobs', ['edit' => $job->id]) }}">تعديل البطاقة</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد وظائف</td></tr>
                @endforelse
            </x-ds-table>
        </section>
        @if ($jobCard)
            <section class="ds-section">
                <h2 class="ds-section-title">بطاقة الوظيفة — {{ $jobCard->name }}</h2>
                <p>الغرض: {{ $jobCard->job_purpose ?? '—' }}</p>
            </section>
        @endif
    @endif

    @if ($tab === 'transfers')
        @can('structure.manage')
            <section class="ds-section">
                <h2 class="ds-section-title">نقل موظف</h2>
                <x-ds-form-group label="الموظف" :error="$errors->first('transferUserId')">
                    <select class="ds-input" wire:model="transferUserId">
                        <option value="">—</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-ds-form-group>
                <x-ds-form-group label="الوحدة التنظيمية الجديدة">
                    <select class="ds-input" wire:model="transferUnitId">
                        <option value="">—</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->level }})</option>{{-- المستوى عربي من OrgUnit --}}
                        @endforeach
                    </select>
                </x-ds-form-group>
                <x-ds-form-group label="سبب النقل">
                    <input type="text" class="ds-input" wire:model="transferReason">
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="transfer">نقل</button>
            </section>
        @endcan

        <x-ds-table>
            <x-slot:head>
                <tr><th>الموظف</th><th>من</th><th>إلى</th><th>التاريخ</th><th>السبب</th></tr>
            </x-slot:head>
            @forelse ($transfers as $transfer)
                <tr wire:key="transfer-{{ $transfer->id }}">
                    <td>{{ $transfer->employee?->name ?? '—' }}</td>
                    <td>{{ $transfer->fromUnit?->name ?? '—' }}</td>
                    <td>{{ $transfer->toUnit?->name ?? '—' }}</td>
                    <td dir="ltr">{{ $transfer->effective_on?->format('Y-m-d') }}</td>
                    <td>{{ $transfer->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد عمليات نقل</td></tr>
            @endforelse
        </x-ds-table>
    @endif

    @if ($tab === 'committees')
        @can('structure.manage')
            <section class="ds-section">
                <x-ds-form-group label="اسم اللجنة" :error="$errors->first('committeeName')">
                    <input type="text" class="ds-input" wire:model="committeeName">
                </x-ds-form-group>
                <x-ds-form-group label="اختصاصها">
                    <textarea class="ds-input" wire:model="committeeMandate"></textarea>
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="saveCommittee">إنشاء لجنة</button>
            </section>
        @endcan

        <x-ds-table>
            <x-slot:head>
                <tr><th>اللجنة</th><th>الرئيس</th><th>الأعضاء</th><th>الاجتماعات</th><th>الحالة</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($committees as $committee)
                <tr wire:key="committee-{{ $committee->id }}">
                    <td>{{ $committee->name }}</td>
                    <td>{{ $committee->chair?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $committee->members->count() }}</td>
                    <td class="ds-ltr-num">{{ $committee->meetings_count }}</td>
                    <td><x-ds-status-badge :status="$committee->is_active ? 'نشطة' : 'موقوفة'" /></td>
                    <td>
                        @can('structure.committees.manage')
                            <div class="ds-toolbar-actions" style="flex-wrap:wrap;gap:.35rem">
                                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.committees', ['manage' => $committee->id]) }}">الأعضاء والضيوف</a>
                                @if ($committee->is_active)
                                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deactivateCommittee({{ $committee->id }})">إيقاف</button>
                                @else
                                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="activateCommittee({{ $committee->id }})">تفعيل</button>
                                @endif
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askDeleteCommittee({{ $committee->id }})">حذف</button>
                            </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد لجان</td></tr>
            @endforelse
        </x-ds-table>

        @if ($committeeDeleteConfirmId && $committeeDeleteTarget)
            @teleport('body')
<div class="ds-modal-overlay" wire:key="org-committee-delete-{{ $committeeDeleteConfirmId }}" wire:click.self="cancelDeleteCommittee" wire:keydown.escape.window="cancelDeleteCommittee" style="z-index:1300">
                <div class="ds-modal" role="dialog" aria-modal="true" dir="rtl" wire:click.stop>
                    <div class="ds-modal-header">
                        <h3>تأكيد حذف اللجنة</h3>
                        <button type="button" class="ds-modal-close" wire:click="cancelDeleteCommittee" aria-label="إغلاق">&times;</button>
                    </div>
                    <div class="ds-modal-body">
                        <p>حذف اللجنة «{{ $committeeDeleteTarget->name }}»؟ سيُزال الأعضاء والضيوف من السجل.</p>
                        @if (($committeeDeleteTarget->meetings_count ?? 0) > 0)
                            <p class="ds-badge ds-badge-warning">مرتبط بـ {{ $committeeDeleteTarget->meetings_count }} اجتماع — لن يُسمح بالحذف؛ استخدم الإيقاف بدلاً منه.</p>
                        @endif
                        <div class="ds-toolbar-actions">
                            <button type="button" class="ds-btn ds-btn-outline" wire:click="cancelDeleteCommittee">إلغاء</button>
                            <button
                                type="button"
                                class="ds-btn ds-btn-primary"
                                wire:click="deleteCommittee({{ $committeeDeleteConfirmId }})"
                                @disabled(($committeeDeleteTarget->meetings_count ?? 0) > 0)
                            >تأكيد الحذف</button>
                        </div>
                    </div>
                </div>
            </div>
@endteleport
        @endif
    @endif

    <x-ds-modal :show="$showUnitModal">
        <x-slot:header><h2>وحدة تنظيمية جديدة</h2></x-slot:header>

        <x-ds-form-group label="الاسم" :error="$errors->first('unitName')">
            <input type="text" class="ds-input" wire:model="unitName">
        </x-ds-form-group>

        <x-ds-form-group label="المستوى" :error="$errors->first('unitLevel')">
            <select class="ds-input" wire:model.live="unitLevel">
                @foreach ($childLevels as $level)
                    <option value="{{ $level }}">{{ $level }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        @if ($unitLevel === \App\Models\OrgUnit::LEVEL_ADMINISTRATION)
            <x-ds-form-group label="يتبع لـ" :error="$errors->first('parentId')">
                <select class="ds-input" wire:model="parentId" @if ($topPositions->isNotEmpty()) required @endif>
                    @forelse ($topPositions as $seat)
                        <option value="{{ $seat->id }}">{{ $seat->name }}</option>
                    @empty
                        <option value="">لا يوجد منصب أعلى</option>
                    @endforelse
                </select>
            </x-ds-form-group>
        @endif

        <x-ds-form-group label="غرض الوظيفة (لبطاقة الوظيفة)">
            <textarea class="ds-input" wire:model="jobPurpose"></textarea>
        </x-ds-form-group>

        <x-ds-form-group label="المسؤوليات (سطر لكل مسؤولية)">
            <textarea class="ds-input" wire:model="jobResponsibilities"></textarea>
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showUnitModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveUnit">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>
    <x-ds-modal :show="$showTopModal">
        <x-slot:header><h2>منصب أعلى</h2></x-slot:header>
        <x-ds-form-group label="المسمى" :error="$errors->first('topTitle')">
            <input type="text" class="ds-input" wire:model="topTitle">
        </x-ds-form-group>
        <x-ds-form-group label="الموظف">
            <select class="ds-input" wire:model="topOccupantId">
                <option value="">شاغر</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="الترتيب">
            <input type="number" class="ds-input" wire:model="topOrder" min="0">
        </x-ds-form-group>
        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showTopModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveTopPosition">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>
    <style>
        .org-toolbar { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.6rem; align-items: center; }
        .org-toolbar .ds-input { max-width: 16rem; }
        .org-chart-scroll { overflow-x: auto; overflow-y: hidden; width: 100%; max-width: 100%; }
        .org-chart-stage { display: inline-block; min-width: 100%; transform-origin: top center; }
        .org-chart { display: flex; flex-direction: column; align-items: center; gap: 2rem; list-style: none; margin: 0; padding: 0; position: relative; }
        .org-chart ul { display: flex; flex-direction: row; justify-content: center; list-style: none; margin: 0; padding: 1.4rem 0 0; position: relative; }
        .org-li { display: flex; flex-direction: column; align-items: center; position: relative; padding: 1.4rem 0.45rem 0; }
        .org-chart > .org-li { padding-top: 0; }
        .org-li::before, .org-li::after { content: ''; position: absolute; top: 0; width: 50%; height: 1.4rem; border-top: 2px solid var(--accent, #0F3446); }
        .org-li::before { right: 50%; }
        .org-li::after { left: 50%; border-left: 2px solid var(--accent, #0F3446); }
        .org-li:first-child::after, .org-li:last-child::before { border-top: 0; }
        .org-li:first-child::before { border-right: 2px solid var(--accent, #0F3446); }
        .org-li:only-child::before { border: 0; }
        .org-li:only-child::after { border-top: 0; width: 0; left: 50%; }
        .org-chart > .org-li::before, .org-chart > .org-li::after { display: none; }
        .org-li > ul::before { content: ''; position: absolute; top: 0; left: 50%; border-left: 2px solid var(--accent, #0F3446); height: 1.4rem; }
        .org-card { width: 210px; border-radius: 12px; box-shadow: 0 6px 16px rgba(15, 52, 70, 0.12); background: #fff; padding: 0.7rem; position: relative; text-align: center; }
        .org-card--top { background: #0F3446; color: #fff; border-top: 4px solid #C4A052; }
        .org-card--admin { border-top: 8px solid var(--accent, #1B6B93); }
        .org-card--section { border-inline-start: 4px solid var(--accent, #27A588); text-align: start; }
        .org-card--job { padding: 0.45rem 0.6rem; }
        .org-card.is-vacant { border: 1px dashed #98a2b3; color: #667085; box-shadow: none; }
        .org-card.is-hit { outline: 3px solid #C4A052; }
        .org-card.is-dim { opacity: 0.22; }
        .org-title { background: none; border: 0; color: inherit; font-weight: 700; cursor: pointer; width: 100%; }
        .org-card--top .org-title { color: #fff; }
        .org-person, .org-head { margin: 0.25rem 0 0; font-size: 0.85rem; }
        .org-person { display: flex; gap: 0.35rem; align-items: center; justify-content: center; color: inherit; text-decoration: none; }
        .org-avatar { width: 1.6rem; height: 1.6rem; border-radius: 50%; background: #C4A052; color: #0F3446; display: grid; place-items: center; font-size: 0.75rem; }
        .org-count-chip, .org-role { display: inline-block; margin-top: 0.3rem; font-size: 0.75rem; color: #667085; background: #f2f4f7; border-radius: 999px; padding: 0.1rem 0.45rem; }
        .org-card--top .org-role { background: rgba(255,255,255,.15); color: #fff; }
        .org-toggle { position: absolute; bottom: -0.7rem; left: 50%; transform: translateX(-50%); border: 0; border-radius: 999px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.15); cursor: pointer; z-index: 2; }
        .org-toggle-closed { display: none; }
        .org-li.is-collapsed > ul { display: none; }
        .org-li.is-collapsed > .org-card .org-toggle-open { display: none; }
        .org-li.is-collapsed > .org-card .org-toggle-closed { display: inline; }
        .org-unlinked { margin-top: 1.5rem; padding: 0.8rem; border: 1px solid #F5C16C; background: #FFF8EB; border-radius: 12px; }
        .org-unlinked-row { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-top: 0.5rem; }
        .org-drawer-overlay { position: fixed; inset: 0; z-index: 1300; background: rgba(0, 44, 61, 0.45); }
        .org-drawer { position: fixed; top: 0; left: 0; height: 100vh; width: min(24rem, 100%); z-index: 1301; background: #fff; box-shadow: 0 0 24px rgba(0,0,0,.15); padding: 1rem; overflow: auto; }
        @media (max-width: 1023px) {
            .org-chart-scroll { overflow-x: hidden; }
            .org-chart-stage { transform: none !important; display: block; width: 100%; }
            .org-toolbar [data-org-zoom] { display: none; }
            .org-toolbar { position: sticky; top: 0; background: #fff; z-index: 5; }
            .org-chart, .org-chart ul { display: block; padding: 0 16px 0 0; border-inline-end: 2px solid var(--accent, #0F3446); }
            .org-chart { border: 0; padding: 0; }
            .org-li { display: block; padding: 0.45rem 0 0; }
            .org-li::before, .org-li::after, .org-li > ul::before { display: none; }
            .org-li { position: relative; }
            .org-li::before { display: block; content: ''; position: absolute; top: 1.2rem; inset-inline-end: -16px; width: 16px; height: 0; border-top: 2px solid var(--accent, #0F3446); border-left: 0; }
            .org-chart > .org-li::before { display: none; }
            .org-card { width: 100%; max-width: 100%; }
            .org-drawer { top: auto; bottom: 0; left: 0; right: 0; width: 100%; height: auto; max-height: 85vh; }
        }
        @media print {
            @page { size: A3 landscape; margin: 8mm; }
            body * { visibility: hidden; }
            .org-print-root, .org-print-root * { visibility: visible; }
            .org-print-root { position: absolute; inset: 0; }
            .org-toolbar, .org-toggle { display: none !important; }
            .org-chart-scroll { overflow: visible; }
        }
    </style>
    </x-ds-page>
