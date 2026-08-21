<x-ds-page>
    <x-ds-page-header
        title="دليل الحسابات"
        :show-button="true"
        button-label="حساب جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <p class="ds-text-muted ds-mb-3">شجرة الحسابات المحاسبية — تربط بنود الصرف والإيراد دون هدم الحركات الحالية.</p>

    <div class="ds-coa-tree">
        @forelse ($roots as $root)
            @include('livewire.finance.partials.coa-node', ['account' => $root, 'depth' => 0])
        @empty
            <x-ds-empty-state message="لا توجد حسابات — شغّل بذر دليل الحسابات" icon="fa-sitemap" />
        @endforelse
    </div>

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showModal', false)">
            <div class="ds-modal" role="dialog">
                <div class="ds-modal-header">
                    <h3>{{ $editingId ? 'تعديل حساب' : 'حساب جديد' }}</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-grid-2">
                        <x-ds-form-group label="رقم الحساب" :error="$errors->first('code')">
                            <input type="text" class="ds-input ds-ltr-num" wire:model="code" dir="ltr">
                        </x-ds-form-group>
                        <x-ds-form-group label="اسم الحساب" :error="$errors->first('name_ar')">
                            <input type="text" class="ds-input" wire:model="name_ar">
                        </x-ds-form-group>
                        <x-ds-form-group label="النوع" :error="$errors->first('type')">
                            <select class="ds-input" wire:model="type">
                                @foreach ($types as $t)
                                    <option value="{{ $t }}">{{ str_replace('_', ' ', $t) }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="الطبيعة" :error="$errors->first('nature')">
                            <select class="ds-input" wire:model="nature">
                                <option value="مدين">مدين</option>
                                <option value="دائن">دائن</option>
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="الحساب الأب" :error="$errors->first('parent_id')">
                            <select class="ds-input" wire:model="parent_id">
                                <option value="">— جذر —</option>
                                @foreach ($parents as $p)
                                    @if ($editingId !== $p->id)
                                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name_ar }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <label class="ds-checkbox-label">
                            <input type="checkbox" wire:model="is_active">
                            <span>نشط</span>
                        </label>
                    </div>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showModal', false)">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
