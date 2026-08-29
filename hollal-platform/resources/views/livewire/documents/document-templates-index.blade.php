<x-ds-page>
    <x-ds-page-header title="مكتبة النماذج" />

    @if ($canManage)
        <section class="ds-section">
            <h2 class="ds-section-title">رفع نموذج معتمد</h2>
            <x-ds-form-group label="العنوان" :error="$errors->first('title')">
                <input type="text" class="ds-input" wire:model="title">
            </x-ds-form-group>
            <x-ds-form-group label="التصنيف" :error="$errors->first('category')">
                <input type="text" class="ds-input" wire:model="category">
            </x-ds-form-group>
            <x-ds-form-group label="من يظهر له النموذج" :error="$errors->first('visibility')">
                <select class="ds-input" wire:model="visibility">
                    <option value="all">جميع المخوّلين بالمستندات</option>
                    <option value="department">إدارة الرافع فقط</option>
                </select>
            </x-ds-form-group>
            <x-ds-form-group label="الوصف" :error="$errors->first('description')">
                <textarea class="ds-input" wire:model="description"></textarea>
            </x-ds-form-group>
            <x-ds-form-group label="الملف" :error="$errors->first('uploadFile')">
                <input type="file" class="ds-input" wire:model="uploadFile">
            </x-ds-form-group>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ النموذج</button>
        </section>
    @endif

    <section class="ds-section">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>العنوان</th>
                    <th>التصنيف</th>
                    <th>الظهور</th>
                    <th>الوصف</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($templates as $template)
                <tr wire:key="tpl-{{ $template->id }}">
                    <td>{{ $template->title }}</td>
                    <td>{{ $template->category ?? '—' }}</td>
                    <td>{{ $template->visibility === 'department' ? 'إدارة الرافع' : 'الجميع' }}</td>
                    <td>{{ $template->description ?? '—' }}</td>
                    <td>
                        <div class="ds-action-icons">
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.templates.download', ['template' => $template, 'inline' => 1]) }}" target="_blank" rel="noopener" title="معاينة">معاينة</a>
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.templates.download', $template) }}" title="تنزيل">تنزيل</a>
                            @if ($canManage)
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="delete({{ $template->id }})">أرشفة</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد نماذج بعد</td></tr>
            @endforelse
        </x-ds-table>
        {{ $templates->links() }}
    </section>
</x-ds-page>
