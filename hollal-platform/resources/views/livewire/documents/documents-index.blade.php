<x-ds-page>
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

    <p class="ds-mb-3">
        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.versions') }}">إدارة النسخ</a>
        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.templates') }}">مكتبة القوالب</a>
        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.policies') }}">السياسات</a>
    </p>
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
                    <th scope="col">العنوان</th>
                    <th scope="col">التصنيف</th>
                    <th scope="col">المشروع</th>
                    <th scope="col">السرية</th>
                    <th scope="col">الرافع</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($documents as $document)
                <tr wire:key="doc-{{ $document->id }}">
                    <td>{{ $document->title }}</td>
                    <td>{{ $document->category }}</td>
                    <td>{{ $document->project?->name ?? '—' }}</td>
                    <td>{{ $confidentialityLabels[$document->confidentiality] ?? $document->confidentiality }}</td>
                    <td>{{ $document->uploader?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $document->created_at?->format('Y-m-d') }}</td>
                    <td>
                        <div class="ds-action-icons">
                            @can('download', $document)
                                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.files.download', $document) }}" title="تحميل" aria-label="تحميل المستند">
                                    <i class="fas fa-download" aria-hidden="true"></i>
                                </a>
                            @endcan
                            @can('delete', $document)
                                <button type="button" class="ds-btn ds-btn-danger ds-btn-sm" wire:click="delete({{ $document->id }})" wire:confirm="حذف هذا المستند؟" title="حذف" aria-label="حذف المستند">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7"><x-ds-empty-state message="لا توجد مستندات" icon="fa-folder-open" /></td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $documents->links() }}

    <x-ds-modal :show="$showUploadModal" title="رفع مستند" size="lg" close-action="closeUploadModal">
        <x-slot:body>
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
        </x-slot:body>
        <x-slot:footer>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveUpload">
                <i class="fas fa-upload" aria-hidden="true"></i> رفع
            </button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="closeUploadModal">إغلاق</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>