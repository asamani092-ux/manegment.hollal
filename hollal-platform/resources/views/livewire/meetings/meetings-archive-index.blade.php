<x-ds-page>
    <x-ds-page-header title="أرشيف المحاضر" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">مسار تعديل المحضر المعتمد: طلب ← موافقة ← نسخة موسومة (رقم الإصدار).</p>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="arch-search">العنوان</label>
            <input id="arch-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بعنوان الاجتماع…">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="arch-month">الشهر</label>
            <input id="arch-month" type="month" class="ds-input ds-ltr-num" wire:model.live="month">
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الاجتماع</th>
                <th scope="col">التاريخ</th>
                <th scope="col">حالة الاعتماد</th>
                <th scope="col">الإصدار</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($meetings as $meeting)
            <tr wire:key="arch-{{ $meeting->id }}">
                <td>{{ $meeting->title }}</td>
                <td class="ds-ltr-num">{{ $meeting->scheduled_at?->format('Y-m-d') }}</td>
                <td><x-ds-status-badge :status="$meeting->approval_status" /></td>
                <td class="ds-ltr-num">{{ $meeting->version }}</td>
                <td>
                    @can('meetings.view')
                        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('meetings.minutes', $meeting) }}">المحضر</a>
                        @if ($meeting->signed_document_id)
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('meetings.minutes.signed', $meeting) }}">النسخة الموقعة</a>
                        @endif
                    @endcan
                    @can('meetings.update')
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="openAmendRequest({{ $meeting->id }})">طلب تعديل</button>
                    @endcan
                    @foreach ($meeting->amendments as $amendment)
                        <p class="ds-text-muted" wire:key="amd-{{ $amendment->id }}">
                            نسخة {{ $amendment->version }} — {{ $amendment->status }}
                            @if ($amendment->status === \App\Models\MeetingAmendment::STATUS_PENDING)
                                @can('meetings.update')
                                    <button type="button" class="ds-btn ds-btn-sm" wire:click="approveAmendment({{ $amendment->id }})">موافقة</button>
                                @endcan
                            @endif
                        </p>
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ds-empty-state message="لا توجد محاضر معتمدة" icon="fa-box-archive" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $meetings->links() }}

    <x-ds-modal :show="$showAmendModal">
        <x-slot:header><h2>طلب تعديل المحضر</h2></x-slot:header>
        <p class="ds-text-muted">الطلب ينتظر الموافقة ثم تُوسم نسخة جديدة.</p>
        <x-ds-form-group label="سبب التعديل" :error="$errors->first('amendNote')">
            <textarea class="ds-input" wire:model="amendNote" rows="3"></textarea>
        </x-ds-form-group>
        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showAmendModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="submitAmendRequest">إرسال الطلب</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
