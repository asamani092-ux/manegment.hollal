<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BudgetThresholdAlert;
use App\Support\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * 04-B6 — per-project budget consumption (all figures derived live) and
 * threshold alerts at 80% / 100%.
 */
class BudgetService
{
    /** @return array<string, float|int> */
    public function consumption(Project $project): array
    {
        $paid = (float) $project->expenseRequests()->where('status', 'paid')->sum('amount');
        $consumed = (float) $project->expenseRequests()->countedAsSpend()->sum('amount');
        $committed = $consumed - $paid;
        $budget = (float) ($project->budget ?? 0);
        $remaining = $budget - $consumed;
        $percent = $budget > 0 ? (int) floor(($consumed / $budget) * 100) : 0;

        return [
            'budget' => $budget,
            'actual_spend' => $paid,
            'committed' => $committed,
            'consumed' => $consumed,
            'remaining' => $remaining,
            'percent' => $percent,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function board(): Collection
    {
        return Project::query()
            ->whereNotNull('budget')
            ->where('budget', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => ['project' => $project] + $this->consumption($project))
            ->values();
    }

    public function warningThreshold(): int
    {
        return (int) round(((float) Setting::get('finance.budget_alert_threshold', 0.8)) * 100);
    }

    /**
     * The tier a consumption percentage falls into: 100, the configured warning
     * threshold (default 80), or null when still below both.
     */
    public function tierFor(int $percent): ?int
    {
        if ($percent >= 100) {
            return 100;
        }

        $warning = $this->warningThreshold();

        return $percent >= $warning ? $warning : null;
    }

    /**
     * Fire alerts for every project that crossed the warning (80%) or full (100%)
     * tier. Recipients: the finance team plus the project manager.
     *
     * @return list<array{project_id: int, tier: int, percent: int}>
     */
    public function fireThresholdAlerts(): array
    {
        $alerted = [];

        foreach ($this->board() as $row) {
            $tier = $this->tierFor((int) $row['percent']);

            if ($tier === null) {
                continue;
            }

            /** @var Project $project */
            $project = $row['project'];

            Notification::send(
                $this->recipientsFor($project),
                new BudgetThresholdAlert($project, (int) $row['percent'], $tier)
            );

            $alerted[] = [
                'project_id' => $project->id,
                'tier' => $tier,
                'percent' => (int) $row['percent'],
            ];
        }

        return $alerted;
    }

    /** @return Collection<int, User> */
    private function recipientsFor(Project $project): Collection
    {
        $recipients = Role::query()->where('name', 'Finance')->where('guard_name', 'web')->exists()
            ? User::role('Finance')->get()
            : collect();

        if ($project->manager_id && $manager = User::find($project->manager_id)) {
            $recipients = $recipients->push($manager);
        }

        return $recipients->unique('id')->values();
    }
}
