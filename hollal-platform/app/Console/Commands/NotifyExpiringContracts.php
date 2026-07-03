<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Notifications\ContractExpiring;
use App\Support\ContractNotificationHelper;
use Illuminate\Console\Command;

class NotifyExpiringContracts extends Command
{
    protected $signature = 'contracts:notify-expiring';

    protected $description = 'Notify HR managers when contracts expire in 90, 60, or 30 days';

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

            $contracts = Contract::query()
                ->select(['id', 'employee_id', 'end_date', 'status'])
                ->where('status', 'active')
                ->whereDate('end_date', $targetDate)
                ->with('employee:id,name')
                ->get();

            foreach ($contracts as $contract) {
                foreach ($recipients as $recipient) {
                    if (ContractNotificationHelper::alreadyNotified($recipient, ContractExpiring::class, $contract->id, $days)) {
                        continue;
                    }

                    $recipient->notify(new ContractExpiring($contract, $days));
                    $sent++;
                }
            }
        }

        $this->info("Sent {$sent} contract expiry notification(s).");

        return self::SUCCESS;
    }
}
