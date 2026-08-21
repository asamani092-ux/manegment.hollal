<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18px; }
        body {
            font-family: {{ $pdfDefaultFont ?? 'amiri' }}, 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.55;
            color: #2a3f5f;
            direction: rtl;
            text-align: right;
        }
        h1 { font-size: 16px; color: #005c7b; margin: 0 0 6px; }
        h2 { font-size: 13px; color: #005c7b; margin: 14px 0 6px; border-bottom: 1px solid #e8eef5; padding-bottom: 3px; }
        .zone { margin-bottom: 14px; }
        .meta { color: #788fa0; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e8eef5; padding: 6px 8px; text-align: right; vertical-align: top; }
        th { background: #f8fafb; color: #005c7b; font-size: 11px; }
        .sig-box { min-height: 36px; }
        .header-row { width: 100%; }
        .header-row td { border: none; padding: 2px 0; }
    </style>
</head>
<body>
    {{-- 1: logo + dates --}}
    <div class="zone">
        <table class="header-row">
            <tr>
                <td style="width:40%"><strong>منصة حلّل</strong></td>
                <td style="width:60%; text-align:left" class="meta">
                    تاريخ الاجتماع: {{ $meeting->scheduled_at?->format('Y-m-d') ?? '—' }}<br>
                    تاريخ الطباعة: {{ hollal_dt(now()) }}
                </td>
            </tr>
        </table>
        <h1>محضر اجتماع: {{ $meeting->title }}</h1>
    </div>

    {{-- 2: meeting details --}}
    <div class="zone">
        <h2>تفاصيل الاجتماع</h2>
        <table>
            <tr><th>العنوان</th><td>{{ $meeting->title }}</td></tr>
            <tr><th>الوقت</th><td>{{ hollal_dt($meeting->scheduled_at) }}</td></tr>
            <tr>
                <th>المكان</th>
                <td>
                    @if ($meeting->link)
                        عن بعد
                    @else
                        {{ $meeting->location ?: '—' }}
                    @endif
                </td>
            </tr>
            <tr><th>الرئيس</th><td>{{ $meeting->chair?->name ?? '—' }}</td></tr>
            <tr><th>محرر الاجتماع</th><td>{{ $meeting->secretary?->name ?? '—' }}</td></tr>
            <tr>
                <th>الحضور</th>
                <td>{{ $meeting->attendees->pluck('name')->filter()->implode('، ') ?: '—' }}</td>
            </tr>
        </table>
    </div>

    {{-- 3: agenda --}}
    <div class="zone">
        <h2>جدول الأعمال</h2>
        <table>
            <thead><tr><th>#</th><th>البند</th></tr></thead>
            <tbody>
                @php $agendaLines = collect(preg_split('/\r\n|\r|\n/', (string) $meeting->agenda))->map(fn ($l) => trim($l))->filter()->values(); @endphp
                @forelse ($agendaLines as $i => $line)
                    <tr><td>{{ $i + 1 }}</td><td>{{ $line }}</td></tr>
                @empty
                    <tr><td colspan="2">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 4: minutes items two columns --}}
    <div class="zone">
        <h2>بنود المحضر</h2>
        <table>
            <thead><tr><th>البند</th><th>القرار أو التوصية</th></tr></thead>
            <tbody>
                @forelse ($meeting->items as $item)
                    <tr>
                        <td>{{ $item->topic }}@if ($item->discussion_summary)<br><span class="meta">{{ $item->discussion_summary }}</span>@endif</td>
                        <td>{{ $item->decision ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">لا توجد بنود</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 5: attendance + signatures --}}
    <div class="zone">
        <h2>الحضور والتوقيع</h2>
        <table>
            <thead><tr><th>الاسم</th><th>التوقيع</th></tr></thead>
            <tbody>
                @forelse ($meeting->attendees as $attendee)
                    <tr>
                        <td>{{ $attendee->name }}</td>
                        <td class="sig-box">
                            <x-signature-cell :path="$attendee->pivot->signature_image_path ?? null" :text="$attendee->pivot->signature_text ?? null" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2">—</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($meeting->minutes_missing_signatures_reason)
            <p class="meta">ملاحظة اعتماد مع نقص توقيع: {{ $meeting->minutes_missing_signatures_reason }}</p>
        @endif
    </div>

    @if ($meeting->guests->isNotEmpty())
        <div class="zone">
            <h2>الضيوف الخارجيون</h2>
            <table>
                <thead><tr><th>الاسم</th><th>البريد</th><th>التأكيد</th><th>التوقيع</th></tr></thead>
                <tbody>
                    @foreach ($meeting->guests as $guest)
                        <tr>
                            <td>{{ $guest->name }}</td>
                            <td>{{ $guest->email }}</td>
                            <td>{{ hollal_dt($guest->confirmed_at) }}</td>
                            <td class="sig-box"><x-signature-cell :path="$guest->signature_image_path" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
