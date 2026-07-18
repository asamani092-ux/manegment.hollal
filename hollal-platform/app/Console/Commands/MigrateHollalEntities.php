<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Partnership;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 00-B4 — one-off data migration that extracts organizations from legacy
 * partnership `entity_name` values, reverses the partnership↔project relation
 * onto projects, maps legacy statuses to journey stages, and marks orphan
 * projects as داخلي. Idempotent; supports --dry-run.
 */
class MigrateHollalEntities extends Command
{
    protected $signature = 'migrate:hollal-entities {--dry-run : Show planned changes without writing}';

    protected $description = 'Extract organizations, reverse partnership↔project links, map statuses, mark orphan projects داخلي';

    /** @var array<string, int> legacy status => journey stage */
    private const STATUS_TO_STAGE = [
        'pending_form' => 1, // فرصة
        'negotiation' => 4,  // عرض السعر (أقرب تطابق)
        'active' => 6,       // تعاقد/تنفيذ
        'completed' => 8,    // مغلقة
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'organizations_created' => 0,
            'partnerships_linked_org' => 0,
            'partnerships_staged' => 0,
            'projects_reversed' => 0,
            'orphan_projects_marked' => 0,
        ];

        $callback = function () use (&$stats): void {
            $orgCache = [];

            foreach (Partnership::all() as $partnership) {
                // a/b. Extract organization from entity_name and link it.
                if ($partnership->organization_id === null && filled($partnership->entity_name)) {
                    $name = trim($partnership->entity_name);
                    if (! array_key_exists($name, $orgCache)) {
                        $existing = Organization::where('name', $name)->first();
                        if ($existing) {
                            $orgCache[$name] = $existing->id;
                        } else {
                            $orgCache[$name] = Organization::create(['name' => $name])->id;
                            $stats['organizations_created']++;
                        }
                    }
                    $partnership->organization_id = $orgCache[$name];
                    $stats['partnerships_linked_org']++;
                }

                // d. Map legacy status to journey stage.
                if ($partnership->stage === null && isset(self::STATUS_TO_STAGE[$partnership->status])) {
                    $partnership->stage = self::STATUS_TO_STAGE[$partnership->status];
                    $stats['partnerships_staged']++;
                }

                // c. Reverse the relation onto the project, then null the legacy column.
                if ($partnership->project_id !== null) {
                    $project = Project::find($partnership->project_id);
                    if ($project && $project->partnership_id === null) {
                        $project->partnership_id = $partnership->id;
                        $project->kind = 'شراكة';
                        $project->save();
                        $stats['projects_reversed']++;
                    }
                    $partnership->project_id = null;
                }

                $partnership->save();
            }

            // e. Any project with no partnership is an internal project.
            $stats['orphan_projects_marked'] = Project::whereNull('partnership_id')
                ->where('kind', '!=', 'داخلي')
                ->update(['kind' => 'داخلي']);
        };

        if ($dryRun) {
            // Run inside a transaction and roll back so nothing is persisted.
            DB::beginTransaction();
            try {
                $callback();
            } finally {
                DB::rollBack();
            }
            $this->info('DRY RUN — no changes were written.');
        } else {
            DB::transaction($callback);
            $this->info('Migration applied.');
        }

        $this->table(['التغيير', 'العدد'], collect($stats)->map(
            fn ($count, $key) => [$key, $count]
        )->values()->all());

        return self::SUCCESS;
    }
}
