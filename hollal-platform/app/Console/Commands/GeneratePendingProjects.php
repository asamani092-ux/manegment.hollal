<?php

namespace App\Console\Commands;

use App\Models\ProjectGenerationRequest;
use App\Services\ProjectGenerationRequestService;
use App\Services\ProjectGenerationService;
use Illuminate\Console\Command;

/**
 * 06B-B1 — consume the pending generation requests produced by 05-B7.
 */
class GeneratePendingProjects extends Command
{
    protected $signature = 'projects:generate-pending';

    protected $description = 'Generate projects for every pending partnership generation request';

    public function handle(ProjectGenerationRequestService $requests, ProjectGenerationService $engine): int
    {
        $generated = 0;

        foreach ($requests->pending() as $request) {
            try {
                $project = $engine->generate($request);
                $this->line("request #{$request->id} → project #{$project->id}");
                $generated++;
            } catch (\Throwable $e) {
                $request->forceFill([
                    'status' => ProjectGenerationRequest::STATUS_FAILED,
                    'failure_reason' => $e->getMessage(),
                ])->save();

                $this->error("request #{$request->id} failed: {$e->getMessage()}");
            }
        }

        $this->info($generated.' project(s) generated.');

        return self::SUCCESS;
    }
}
