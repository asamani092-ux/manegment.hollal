<?php

namespace App\Console\Commands;

use App\Services\DocumentLibraryService;
use Illuminate\Console\Command;

/**
 * 07-B1 — daily policy review-date sweep.
 */
class CheckPolicyReviewDates extends Command
{
    protected $signature = 'documents:check-policy-reviews';

    protected $description = 'Alert document owners about policies that reached their review date';

    public function handle(DocumentLibraryService $documents): int
    {
        $alerted = $documents->firePolicyReviewAlerts();

        $this->info(count($alerted).' policy review alert(s) sent.');

        return self::SUCCESS;
    }
}
