<?php

namespace App\Notifications;

use App\Models\ExpenseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExpensePaidReady extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\SendsToPreferredChannels;

    public function __construct(public ExpenseRequest $expenseRequest) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'طلب المصروف جاهز للدفع',
            'expense_request_id' => $this->expenseRequest->id,
            'amount' => (string) $this->expenseRequest->amount,
            'paid_ready_at' => $this->expenseRequest->paid_ready_at?->toIso8601String(),
        ];
    }
}
