<div>
    @php
        $confidentialityLabels = ['team' => 'الفريق', 'department' => 'القسم', 'managers' => 'المدراء'];
    @endphp

    <x-ds-page-header
        title="المستندات"
        :show-button="auth()->user()->can('documents.create')"
        button-label="رفع مستند"
        button-icon="fa-upload"
        wire:click="openUpload"
    />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">بحث</label>
            <input type="search" class="ds-input" wire:model.live.debounce.300ms="search" placeholder="عنوان المستند...">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label">التصنيف</label>
            <select class="ds-input" wire:model.live="categoryFilter">
                <option value="">— الكل —</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="ds-table-wrap">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>العنوان</th>
                    <th>التصنيف</th>
                    <th>المشروع</th>
                    <th>السرية</th>
                    <th>الرافع</th>
                    <th>التاريخ</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($documents as $document)
                <tr wire:key="doc-{{ $document->id }}">
                    <td>{{ $document->title }}</td>
                    <td>{{ $document->category }}</td>
                    <td>{{ $document->project?->name ?? '—' }}</td>
                    <td>{{ $confidentialityLabels[$document->confidentiality] ?? $document->confidentiality }}</td>
                    <td>{{ $document->uploader?->name ?? '—' }}</td>
                    <td>{{ $document->created_at?->format('Y-m-d') }}</td>
                    <td>
                        <div class="ds-action-icons">
                            @can('download', $document)
                                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.files.download', $document) }}" title="تحميل">
                                    <i class="fas fa-download"></i>
                                </a>
                            @endcan
                            @can('delete', $document)
                                <button type="button" class="ds-btn ds-btn-danger ds-btn-sm" wire:click="delete({{ $document->id }})" wire:confirm="حذف هذا المستند؟" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="ds-text-muted ds-table-empty">لا توجد مستندات</td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $documents->links() }}

    @if ($showUploadModal)
        <div class="ds-modal-overlay" wire:click.self="closeUploadModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>رفع مستند</h3>
                    <button type="button" class="ds-modal-close" wire:click="closeUploadModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-grid-2">
                        <x-ds-form-group label="العنوان" :error="$errors->first('title')">
                            <input type="text" class="ds-input" wire:model="title">
                        </x-ds-form-group>
                        <x-ds-form-group label="التصنيف" :error="$errors->first('category')">
                            <input type="text" class="ds-input" wire:model="category" placeholder="مثال: عقود، تقارير...">
                        </x-ds-form-group>
                        <x-ds-form-group label="المشروع">
                            <select class="ds-input" wire:model="project_id">
                                <option value="">— بدون —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="مستوى السرية" :error="$errors->first('confidentiality')">
                            <select class="ds-input" wire:model="confidentiality">
                                @foreach ($confidentialityLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                    </div>
                    <x-ds-form-group label="الملف" :error="$errors->first('uploadFile')">
                        <input type="file" class="ds-input" wire:model="uploadFile">
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="saveUpload">
                        <i class="fas fa-upload"></i> رفع
                    </button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeUploadModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</div>
