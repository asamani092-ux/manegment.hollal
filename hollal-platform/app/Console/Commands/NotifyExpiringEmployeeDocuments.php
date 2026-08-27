<?php

namespace App\Console\Commands;

use App\Models\EmployeeDocument;
use App\Notifications\EmployeeDocumentExpiring;
use App\Support\ContractNotificationHelper;
use Illuminate\Console\Command;

/**
 * Notify HR when official employee documents expire in 90/60/30 days.
 * Time: O(n) documents × recipients | Space: O(1)
 */
class NotifyExpiringEmployeeDocuments extends Command
{
    protected $signature = 'hr:notify-expiring-documents';

    protected $description = 'Notify HR when employee official documents expire in 90, 60, or 30 days';

    /** @var list<int> */
    protected array $thresholds = [90, 60, 30];

    public function handle(): int
    {
        $recipients = ContractNotificationHelper::hrManagers();

        if ($recipients->isEmpty()) {
            $this->warn('No HR managers found to notify.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($this->thresholds as $days) {
            $targetDate = now()->startOfDay()->addDays($days);

            $documents = EmployeeDocument::query()
                ->select(['id', 'user_id', 'type', 'expiry_date', 'document_number'])
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', $targetDate)
                ->with('user:id,name')
                ->get();

            foreach ($documents as $document) {
                foreach ($recipients as $recipient) {
                    $already = $recipient->notifications()
                        ->where('type', EmployeeDocumentExpiring::class)
                        ->where('data->employee_document_id', $document->id)
                        ->where('data->days_remaining', $days)
                        ->exists();

                    if ($already) {
                        continue;
                    }

                    $recipient->notify(new EmployeeDocumentExpiring($document, $days));
                    $sent++;
                }
            }
        }

        $this->info("Sent {$sent} employee document expiry notification(s).");

        return self::SUCCESS;
    }
}
