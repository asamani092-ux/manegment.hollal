<div>
    @php
        $projectStatusLabels = ['active' => 'نشط', 'completed' => 'مكتمل', 'on_hold' => 'متوقف'];
        $partnershipStatusLabels = ['pending_form' => 'بانتظار النموذج', 'negotiation' => 'تفاوض', 'active' => 'نشطة', 'completed' => 'مكتملة'];
    @endphp

    {{-- Projects --}}
    <section class="ds-section-spaced">
        <div class="ds-page-toolbar">
            <h2 class="ds-section-heading">المشاريع</h2>
            <div class="ds-toolbar-actions">
                @can('projects.create')
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="openProjectCreate">
                        <i class="fas fa-plus"></i> مشروع جديد
                    </button>
                @endcan
            </div>
        </div>

        <div class="ds-filters-row">
            <div class="ds-filter-field">
                <label class="ds-label">بحث</label>
                <input type="search" class="ds-input" wire:model.live.debounce.300ms="projectSearch" placeholder="اسم المشروع...">
            </div>
        </div>

        <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>الاسم</th>
                        <th>المدير</th>
                        <th>الحالة</th>
                        <th>الميزانية</th>
                        <th>الإنفاق الفعلي</th>
                        <th>المتبقي</th>
                        <th>المرحلة</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($projects as $project)
                    <tr wire:key="project-{{ $project->id }}">
                        <td>
                            <a href="{{ route('projects.show', $project) }}" class="ds-link">{{ $project->name }}</a>
                        </td>
                        <td>{{ $project->manager?->name ?? '—' }}</td>
                        <td>{{ $projectStatusLabels[$project->status] ?? $project->status }}</td>
                        <td>{{ $project->budget !== null ? number_format((float) $project->budget, 2) : '—' }}</td>
                        <td>{{ number_format((float) ($project->actual_spend ?? 0), 2) }}</td>
                        <td>
                            @if ($project->budget !== null)
                                {{ number_format((float) $project->budget - (float) ($project->actual_spend ?? 0), 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $project->current_phase ?: '—' }}</td>
                        <td>
                            <x-ds-action-icons
                                :show-view="auth()->user()->can('projects.view')"
                                :show-edit="auth()->user()->can('projects.update')"
                                :show-delete="auth()->user()->can('projects.delete')"
                                :view-action="'openProjectView('.$project->id.')'"
                                :edit-action="'openProjectEdit('.$project->id.')'"
                                :delete-action="'deleteProject('.$project->id.')'"
                                delete-confirm="حذف هذا المشروع؟"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="ds-text-muted ds-table-empty">لا توجد مشاريع</td>
                    </tr>
                @endforelse
            </x-ds-table>

        {{ $projects->links() }}
    </section>

    {{-- Partnerships --}}
    @can('partnerships.view')
    <section class="ds-section-spaced">
        <div class="ds-page-toolbar">
            <h2 class="ds-section-heading">الشراكات</h2>
            <div class="ds-toolbar-actions">
                @can('partnerships.create')
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="openPartnershipCreate">
                        <i class="fas fa-handshake"></i> شراكة جديدة
                    </button>
                @endcan
            </div>
        </div>

        <div class="ds-filters-row">
            <div class="ds-filter-field">
                <label class="ds-label">بحث</label>
                <input type="search" class="ds-input" wire:model.live.debounce.300ms="partnershipSearch" placeholder="اسم الجهة...">
            </div>
        </div>

        <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>الجهة</th>
                        <th>المشروع</th>
                        <th>الحالة</th>
                        <th>المبلغ</th>
                        <th>رابط الضيف</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($partnerships as $partnership)
                    <tr wire:key="partnership-{{ $partnership->id }}">
                        <td>{{ $partnership->entity_name }}</td>
                        <td>{{ $partnership->project?->name ?? '—' }}</td>
                        <td>{{ $partnershipStatusLabels[$partnership->status] ?? $partnership->status }}</td>
                        <td>{{ $partnership->pricing_amount !== null ? number_format((float) $partnership->pricing_amount, 2) : '—' }}</td>
                        <td>
                            @if ($partnership->magic_link_token && $partnership->token_expires_at?->isFuture())
                                <a class="ds-link" href="{{ route('partnership.guest', $partnership->magic_link_token) }}" target="_blank" rel="noopener">فتح</a>
                            @else
                                <span class="ds-text-muted">منتهي</span>
                            @endif
                        </td>
                        <td>
                            <x-ds-action-icons
                                :show-view="auth()->user()->can('partnerships.view')"
                                :show-edit="auth()->user()->can('partnerships.update')"
                                :show-delete="auth()->user()->can('partnerships.delete')"
                                :view-action="'openPartnershipView('.$partnership->id.')'"
                                :edit-action="'openPartnershipEdit('.$partnership->id.')'"
                                :delete-action="'deletePartnership('.$partnership->id.')'"
                                delete-confirm="حذف هذه الشراكة؟"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="ds-text-muted ds-table-empty">لا توجد شراكات</td>
                    </tr>
                @endforelse
            </x-ds-table>

        {{ $partnerships->links() }}
    </section>
    @endcan

    {{-- Project modal --}}
    @if ($showProjectModal)
        <div class="ds-modal-overlay" wire:click.self="closeProjectModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>
                        @if ($projectViewOnly)
                            عرض مشروع
                        @elseif ($projectId)
                            تعديل مشروع
                        @else
                            مشروع جديد
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeProjectModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-grid-2">
                        <x-ds-form-group label="اسم المشروع" :error="$errors->first('name')">
                            <input type="text" class="ds-input" wire:model="name" @disabled($projectViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="المدير">
                            <select class="ds-input" wire:model="manager_id" @disabled($projectViewOnly)>
                                <option value="">— بدون —</option>
                                @foreach ($managers as $mgr)
                                    <option value="{{ $mgr->id }}">{{ $mgr->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="تاريخ البداية" :error="$errors->first('start_date')">
                            <input type="date" class="ds-input" wire:model="start_date" @disabled($projectViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="تاريخ النهاية" :error="$errors->first('end_date')">
                            <input type="date" class="ds-input" wire:model="end_date" @disabled($projectViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الميزانية" :error="$errors->first('budget')">
                            <input type="number" step="0.01" class="ds-input" wire:model="budget" @disabled($projectViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الحالة" :error="$errors->first('status')">
                            <select class="ds-input" wire:model="status" @disabled($projectViewOnly)>
                                @foreach ($projectStatusLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="المرحلة الحالية" :error="$errors->first('current_phase')">
                            <input type="text" class="ds-input" wire:model="current_phase" @disabled($projectViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الجمهور المستهدف" :error="$errors->first('target_audience')">
                            <input type="text" class="ds-input" wire:model="target_audience" @disabled($projectViewOnly)>
                        </x-ds-form-group>
                    </div>
                    <x-ds-form-group label="فكرة / هدف المشروع" :error="$errors->first('idea_goal')">
                        <textarea class="ds-input" rows="3" wire:model="idea_goal" @disabled($projectViewOnly)></textarea>
                    </x-ds-form-group>
                    <x-ds-form-group label="المخرجات المطلوبة" :error="$errors->first('required_outputs')">
                        <textarea class="ds-input" rows="3" wire:model="required_outputs" @disabled($projectViewOnly)></textarea>
                    </x-ds-form-group>
                    <x-ds-form-group label="المخرجات النهائية" :error="$errors->first('final_outputs')">
                        <textarea class="ds-input" rows="3" wire:model="final_outputs" @disabled($projectViewOnly)></textarea>
                    </x-ds-form-group>

                    @if ($projectViewOnly && $projectId)
                        @php
                            $viewProject = \App\Models\Project::withSum(['expenseRequests as actual_spend' => fn ($q) => $q->countedAsSpend()], 'amount')->find($projectId);
                        @endphp
                        @if ($viewProject)
                            <div class="ds-detail-row">
                                <span class="ds-detail-label">الإنفاق الفعلي:</span>
                                <span>{{ number_format((float) ($viewProject->actual_spend ?? 0), 2) }}</span>
                            </div>
                            @if ($viewProject->budget !== null)
                                <div class="ds-detail-row">
                                    <span class="ds-detail-label">المتبقي من الميزانية:</span>
                                    <span>{{ number_format((float) $viewProject->budget - (float) ($viewProject->actual_spend ?? 0), 2) }}</span>
                                </div>
                            @endif
                        @endif
                    @endif

                    @if ($projectId && auth()->user()->can('projects.update'))
                        <div class="ds-form-group">
                            <label class="ds-label">فريق المشروع</label>
                            <div class="ds-permissions-grid">
                                @foreach ($allUsers as $user)
                                    <label class="ds-checkbox-label" wire:key="team-user-{{ $user->id }}">
                                        <input type="checkbox" value="{{ $user->id }}" wire:model="teamUserIds" @disabled($projectViewOnly)>
                                        <span>{{ $user->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if (! $projectViewOnly)
                                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="syncProjectTeam">
                                    حفظ الفريق
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="ds-modal-footer">
                    @if (! $projectViewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveProject">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeProjectModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Partnership modal --}}
    @if ($showPartnershipModal)
        <div class="ds-modal-overlay" wire:click.self="closePartnershipModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>
                        @if ($partnershipViewOnly)
                            عرض شراكة
                        @elseif ($partnershipId)
                            تعديل شراكة
                        @else
                            شراكة جديدة
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closePartnershipModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-grid-2">
                        <x-ds-form-group label="اسم الجهة" :error="$errors->first('entity_name')">
                            <input type="text" class="ds-input" wire:model="entity_name" @disabled($partnershipViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="المشروع">
                            <select class="ds-input" wire:model="partnership_project_id" @disabled($partnershipViewOnly)>
                                <option value="">— بدون —</option>
                                @foreach ($allProjects as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="مسؤول التواصل" :error="$errors->first('contact_person')">
                            <input type="text" class="ds-input" wire:model="contact_person" @disabled($partnershipViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="جوال التواصل" :error="$errors->first('contact_phone')">
                            <input type="tel" class="ds-input" wire:model="contact_phone" @disabled($partnershipViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="النوع / الكمية" :error="$errors->first('type_quantity')">
                            <input type="text" class="ds-input" wire:model="type_quantity" @disabled($partnershipViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="المبلغ" :error="$errors->first('pricing_amount')">
                            <input type="number" step="0.01" class="ds-input" wire:model="pricing_amount" @disabled($partnershipViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الحالة" :error="$errors->first('partnership_status')">
                            <select class="ds-input" wire:model="partnership_status" @disabled($partnershipViewOnly)>
                                @foreach ($partnershipStatusLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="رابط العقد (PDF)" :error="$errors->first('contract_pdf')">
                            <input type="text" class="ds-input" wire:model="contract_pdf" @disabled($partnershipViewOnly)>
                        </x-ds-form-group>
                    </div>
                    <x-ds-form-group label="التزامات حلال" :error="$errors->first('halal_commitments')">
                        <textarea class="ds-input" rows="3" wire:model="halal_commitments" @disabled($partnershipViewOnly)></textarea>
                    </x-ds-form-group>
                    <x-ds-form-group label="التزامات الشريك" :error="$errors->first('partner_commitments')">
                        <textarea class="ds-input" rows="3" wire:model="partner_commitments" @disabled($partnershipViewOnly)></textarea>
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    @if (! $partnershipViewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="savePartnership">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closePartnershipModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</div>
