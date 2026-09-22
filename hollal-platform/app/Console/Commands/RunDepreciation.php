<?php

namespace App\Console\Commands;

use App\Services\DepreciationService;
use Illuminate\Console\Command;

/**
 * تشغيل قيود الإهلاك الشهري بالقسط الثابت.
 */
class RunDepreciation extends Command
{
    protected $signature = 'finance:run-depreciation {month? : الشهر بصيغة Y-m}';

    protected $description = 'توليد قيود الإهلاك الشهرية للأصول النشطة';

    public function handle(DepreciationService $service): int
    {
        $month = $this->argument('month') ?: now()->format('Y-m');
        $count = $service->runMonthlyDepreciation($month);
        $this->info("نُشر {$count} قيد إهلاك لشهر {$month}");

        return self::SUCCESS;
    }
}
