<x-ds-page>
    <x-ds-page-header title="مكتبة النماذج">
        <x-slot:actions>
            <a href="{{ route('documents.index') }}" class="ds-btn ds-btn-outline">المستندات</a>
        </x-slot:actions>
    </x-ds-page-header>

    @if ($canManage)
        <section class="ds-section">
            <h2 class="ds-section-title">رفع نموذج معتمد</h2>
            <x-ds-form-group label="العنوان" :error="$errors->first('title')">
                <input type="text" class="ds-input" wire:model="title">
            </x-ds-form-group>
            <x-ds-form-group label="التصنيف" :error="$errors->first('category')">
                <input type="text" class="ds-input" wire:model="category">
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
                    <th>الوصف</th>
                    @if ($canManage)<th></th>@endif
                </tr>
            </x-slot:head>
            @forelse ($templates as $template)
                <tr wire:key="tpl-{{ $template->id }}">
                    <td>{{ $template->title }}</td>
                    <td>{{ $template->category ?? '—' }}</td>
                    <td>{{ $template->description ?? '—' }}</td>
                    @if ($canManage)
                        <td>
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="delete({{ $template->id }})">أرشفة</button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 4 : 3 }}" class="ds-text-muted ds-table-empty">لا توجد نماذج بعد</td></tr>
            @endforelse
        </x-ds-table>
        {{ $templates->links() }}
    </section>
</x-ds-page>
