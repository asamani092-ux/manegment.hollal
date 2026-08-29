<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 20px; }
        {!! $fontFaceCss !!}
        h1 { font-size: 16px; color: #0F3446; margin: 0 0 8px; text-align: right; }
        h2 { font-size: 13px; color: #0F3446; margin: 16px 0 6px; text-align: right; border-bottom: 1px solid #0F3446; padding-bottom: 3px; }
        .zone { margin-bottom: 14px; }
        .meta { color: #5a6f7e; font-size: 11px; }
        .sig-box { min-height: 40px; }
        table.pdf-header { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.pdf-header td { border: none; padding: 2px 0; vertical-align: top; }
        table.pdf-cols { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.pdf-cols th, table.pdf-cols td { border: 1px solid #0F3446; padding: 6px 8px; text-align: right; vertical-align: top; }
        table.pdf-cols th { background: #0F3446; color: #fff; font-size: 11px; }
        table.pdf-cols td.col-num { width: 8%; text-align: center; }
        table.pdf-cols td.col-topic { width: 45%; }
        table.pdf-cols td.col-decision { width: 55%; }
        table.pdf-cols td.col-name { width: 50%; }
        table.pdf-cols td.col-sig { width: 50%; }
    </style>
</head>
<body>
    <div class="zone">
        {!! \App\Support\PdfArabic::header('محضر اجتماع: '.$meeting->title, includeCr: false) !!}
        <table class="pdf-header">
            <tr>
                <td class="meta num" style="text-align:left; width:50%;">
                    تاريخ الاجتماع: <span class="pdf-num">{{ $meeting->scheduled_at?->format('Y-m-d') ?? '—' }}</span><br>
                    تاريخ الطباعة: <span class="pdf-num">{{ hollal_dt(now()) }}</span>
                </td>
                <td style="width:50%;"></td>
            </tr>
        </table>
    </div>

    <div class="zone">
        <h2>تفاصيل الاجتماع</h2>
        <table class="pdf-meta">
            <tr>
                <td>{{ $meeting->title }}</td>
                <td class="pdf-label">العنوان</td>
            </tr>
            <tr>
                <td class="num">{{ hollal_dt($meeting->scheduled_at) }}</td>
                <td class="pdf-label">الوقت</td>
            </tr>
            <tr>
                <td>
                    @if ($meeting->link)
                        عن بعد
                    @else
                        {{ $meeting->location ?: '—' }}
                    @endif
                </td>
                <td class="pdf-label">المكان</td>
            </tr>
            <tr>
                <td>{{ $meeting->chair?->name ?? '—' }}</td>
                <td class="pdf-label">الرئيس</td>
            </tr>
            <tr>
                <td>{{ $meeting->secretary?->name ?? '—' }}</td>
                <td class="pdf-label">محرر الاجتماع</td>
            </tr>
            <tr>
                <td>{{ $meeting->attendees->pluck('name')->filter()->implode('، ') ?: '—' }}</td>
                <td class="pdf-label">الحضور</td>
            </tr>
        </table>
    </div>

    <div class="zone">
        <h2>جدول الأعمال</h2>
        <table class="pdf-cols">
            <thead>
                <tr>
                    <th style="width:8%">#</th>
                    <th>البند</th>
                </tr>
            </thead>
            <tbody>
                @php $agendaLines = collect(preg_split('/\r\n|\r|\n/', (string) $meeting->agenda))->map(fn ($l) => trim($l))->filter()->values(); @endphp
                @forelse ($agendaLines as $i => $line)
                    <tr>
                        <td class="col-num num">{{ $i + 1 }}</td>
                        <td>{{ $line }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="zone">
        <h2>بنود المحضر</h2>
        <table class="pdf-cols">
            <thead>
                <tr>
                    <th class="col-topic">البند</th>
                    <th class="col-decision">القرار أو التوصية</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($meeting->items as $item)
                    <tr>
                        <td class="col-topic">
                            {{ $item->topic }}
                            @if ($item->discussion_summary)
                                <br><span class="meta">{{ $item->discussion_summary }}</span>
                            @endif
                            @if ($item->responsible)
                                <br><span class="meta">المسؤول: {{ $item->responsible->name }}</span>
                            @endif
                        </td>
                        <td class="col-decision">{{ $item->decision ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">لا توجد بنود</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="zone">
        <h2>الحضور والتوقيع</h2>
        <table class="pdf-cols">
            <thead>
                <tr>
                    <th class="col-name">الاسم</th>
                    <th class="col-sig">التوقيع</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($meeting->attendees as $attendee)
                    <tr>
                        <td class="col-name">{{ $attendee->name }}</td>
                        <td class="col-sig sig-box">
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
            <table class="pdf-cols">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>البريد</th>
                        <th>التأكيد</th>
                        <th>التوقيع</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($meeting->guests as $guest)
                        <tr>
                            <td>{{ $guest->name }}</td>
                            <td class="num">{{ $guest->email }}</td>
                            <td class="num">{{ hollal_dt($guest->confirmed_at) }}</td>
                            <td class="sig-box"><x-signature-cell :path="$guest->signature_image_path" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
