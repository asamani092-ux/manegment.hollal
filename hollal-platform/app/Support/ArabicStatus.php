<?php

namespace App\Support;

/**
 * عرض عربي للحالات المخزّنة دون تغيير القيمة.
 * Time: O(1) | Space: O(1)
 */
class ArabicStatus
{
    /** @var array<int, string> */
    public const MONTHS = [
        1 => 'يناير',
        2 => 'فبراير',
        3 => 'مارس',
        4 => 'أبريل',
        5 => 'مايو',
        6 => 'يونيو',
        7 => 'يوليو',
        8 => 'أغسطس',
        9 => 'سبتمبر',
        10 => 'أكتوبر',
        11 => 'نوفمبر',
        12 => 'ديسمبر',
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'مرفوع_للمالية' => 'مرفوع للمالية',
        'معاد_للتصحيح' => 'معاد للتصحيح',
        'منتهية_علاقته' => 'منتهية علاقته',
        'قيد_التقييم' => 'قيد التقييم',
        'draft' => 'مسودة',
        'pending' => 'بانتظار الموافقة',
        'approved' => 'موافق عليه',
        'paid' => 'مدفوع',
        'rejected' => 'مرفوض',
        'returned' => 'معاد للمراجعة',
        'submitted' => 'مرفوع',
        'executed' => 'منفذ',
        'awaiting_statement' => 'بانتظار الإفادة',
        'pending_decision' => 'بانتظار القرار',
        'new' => 'جديدة',
        'in_progress' => 'قيد التنفيذ',
        'pending_review' => 'بانتظار المراجعة',
        'completed' => 'مكتملة',
        'overdue' => 'متأخرة',
        'active' => 'ساري',
        'suspended' => 'موقوف',
        'archived' => 'مؤرشف',
        'suggested' => 'مقترحة',
        'applied' => 'مطبّقة',
        'excluded' => 'مستبعدة',
        'cancelled' => 'ملغاة',
        'reduced' => 'مخففة',
        'recorded' => 'مسجّلة',
    ];

    public static function label(?string $status): string
    {
        $status = trim((string) $status);
        if ($status === '') {
            return '—';
        }

        return self::LABELS[$status] ?? str_replace('_', ' ', $status);
    }

    public static function month(?string $yearMonth): string
    {
        if ($yearMonth === null || ! str_contains($yearMonth, '-')) {
            return '—';
        }
        [$year, $month] = explode('-', $yearMonth, 2);
        $name = self::MONTHS[(int) $month] ?? $month;

        return $name.' '.$year;
    }
}
