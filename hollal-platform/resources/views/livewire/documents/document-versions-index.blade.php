<x-ds-page>
    <x-ds-page-header
        title="إدارة النسخ"
        :show-button="$canUpload"
        button-label="رفع نسخة جديدة"
        button-icon="fa-upload"
        wire:click="openUpload"
    />

    <p class="ds-text-muted ds-mb-3">
        آخر نسخة هي المعروضة في المستودع للتنزيل. رفع نسخة جديدة لا يمحو النسخ القديمة.
    </p>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="ver-doc">المستند</label>
            <select id="ver-doc" class="ds-input" wire:model.live="documentFilter">
                <option value="">— الكل —</option>
                @foreach ($documents as $document)
                    <option value="{{ $document->id }}">{{ $document->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="ver-search">بحث</label>
            <input id="ver-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بعنوان المستند…">
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">المستند</th>
                <th scope="col">النسخة</th>
                <th scope="col">ملاحظة التغيير</th>
                <th scope="col">التاريخ</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($versions as $version)
            @php
                $isCurrent = $version->document
                    && (int) $version->document->current_version === (int) $version->version;
            @endphp
            <tr wire:key="ver-{{ $version->id }}">
                <td>{{ $version->document?->title ?? '—' }}</td>
                <td class="ds-ltr-num">
                    {{ $version->version }}
                    @if ($isCurrent)
                        <span class="ds-badge ds-badge-success">الحالية</span>
                    @endif
                </td>
                <td>{{ $version->change_note ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $version->created_at?->format('Y-m-d') }}</td>
                <td>
                    <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.versions.download', ['version' => $version, 'inline' => 1]) }}" target="_blank" rel="noopener">معاينة</a>
                    <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.versions.download', $version) }}">تنزيل</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ds-empty-state message="لا توجد نسخ مسجّلة" icon="fa-clock-rotate-left" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $versions->links() }}

    <x-ds-modal :show="$showUpload" title="رفع نسخة جديدة" close-action="$set('showUpload', false)">
        <x-ds-form-group label="المستند" :error="$errors->first('document_id')">
            <select class="ds-input" wire:model="document_id">
                <option value="">—</option>
                @foreach ($documents as $document)
                    <option value="{{ $document->id }}">{{ $document->title }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="الملف" :error="$errors->first('uploadFile')">
            <input type="file" class="ds-input" wire:model="uploadFile">
        </x-ds-form-group>
        <x-ds-form-group label="ملاحظة التغيير" :error="$errors->first('change_note')">
            <input type="text" class="ds-input" wire:model="change_note">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveVersion">حفظ النسخة</button>
    </x-ds-modal>
</x-ds-page>
