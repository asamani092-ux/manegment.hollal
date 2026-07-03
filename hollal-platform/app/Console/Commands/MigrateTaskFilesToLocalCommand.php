<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Move legacy task files from the public disk to the private local disk.
 */
class MigrateTaskFilesToLocalCommand extends Command
{
    protected $signature = 'tasks:migrate-files-to-local {--dry-run : Preview changes without writing files or DB updates}';

    protected $description = 'Migrate task attachment_path and submitted_file from public storage to local (private) disk';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $migrated = 0;
        $skipped = 0;
        $missing = 0;

        Task::withTrashed()
            ->where(function ($query) {
                $query->whereNotNull('attachment_path')
                    ->orWhereNotNull('submitted_file');
            })
            ->cursor()
            ->each(function (Task $task) use ($dryRun, &$migrated, &$skipped, &$missing) {
                foreach (['attachment_path', 'submitted_file'] as $column) {
                    $path = $task->{$column};

                    if (! $path) {
                        continue;
                    }

                    if (Storage::disk('local')->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! Storage::disk('public')->exists($path)) {
                        $this->warn("Missing file for task #{$task->id} ({$column}): {$path}");
                        $missing++;

                        continue;
                    }

                    $newPath = $this->uniqueLocalPath(basename($path));

                    if ($dryRun) {
                        $this->line("[dry-run] task #{$task->id} {$column}: {$path} → {$newPath}");
                        $migrated++;

                        continue;
                    }

                    Storage::disk('local')->put($newPath, Storage::disk('public')->get($path));
                    Storage::disk('public')->delete($path);
                    $task->update([$column => $newPath]);

                    $this->info("Migrated task #{$task->id} {$column} → {$newPath}");
                    $migrated++;
                }
            });

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Migrated', $migrated],
                ['Already on local', $skipped],
                ['Missing source', $missing],
            ]
        );

        return self::SUCCESS;
    }

    protected function uniqueLocalPath(string $basename): string
    {
        $candidate = 'tasks/'.$basename;

        while (Storage::disk('local')->exists($candidate)) {
            $candidate = 'tasks/'.Str::uuid()->toString().'_'.$basename;
        }

        return $candidate;
    }
}
