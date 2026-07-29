<x-ds-page>
    <x-ds-page-header
        title="إدارة النسخ"
        :show-button="$canUpload"
        button-label="رفع نسخة جديدة"
        button-icon="fa-upload"
        wire:click="openUpload"
    />

    <p class="ds-text-muted ds-mb-3">
        قسم داخل المستندات — رفع نسخة جديدة لا يمحو النسخ القديمة.
        <a href="{{ route('documents.index') }}">العودة للمستودع</a>
    </p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>المستند</th>
                <th>النسخة</th>
                <th>ملاحظة التغيير</th>
                <th>التاريخ</th>
            </tr>
        </x-slot:head>
        @forelse ($versions as $version)
            <tr wire:key="ver-{{ $version->id }}">
                <td>{{ $version->document?->title ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $version->version }}</td>
                <td>{{ $version->change_note ?? '—' }}</td>
                <td class="ds-ltr-num">{{ $version->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="ds-table-empty">لا توجد نسخ مسجّلة</td></tr>
        @endforelse
    </x-ds-table>
    {{ $versions->links() }}

    @if ($showUpload)
        <div class="ds-modal-overlay" wire:click.self="$set('showUpload', false)">
            <div class="ds-modal" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>رفع نسخة جديدة</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showUpload', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
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
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
