<?php

namespace App\Services;

use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\Program;
use App\Models\ProjectGenerationRequest;
use App\Models\Quote;
use App\Models\User;

/**
 * 05-B7 — «توليد مشروع». Produces the handoff record that 06B-B1's generation
 * engine consumes: program, services sold in the quote, launch date, PM.
 */
class ProjectGenerationRequestService
{
    public function create(
        Partnership $partnership,
        Program $program,
        string $launchDate,
        ?User $projectManager = null,
        ?Quote $quote = null,
        ?User $requestedBy = null,
    ): ProjectGenerationRequest {
        $contract = $partnership->confirmedContract();

        if (! $contract || $contract->status !== PartnershipContract::STATUS_CONFIRMED) {
            throw new \RuntimeException('لا يُولّد مشروع قبل تأكيد التعاقد');
        }

        $quote ??= $contract->quote;

        return ProjectGenerationRequest::create([
            'partnership_id' => $partnership->id,
            'program_id' => $program->id,
            'quote_id' => $quote?->id,
            'included_services' => $quote?->includedServices() ?? [],
            'launch_date' => $launchDate,
            'project_manager_id' => $projectManager?->id,
            'status' => ProjectGenerationRequest::STATUS_PENDING,
            'requested_by' => $requestedBy?->id,
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ProjectGenerationRequest> */
    public function pending()
    {
        return ProjectGenerationRequest::query()
            ->where('status', ProjectGenerationRequest::STATUS_PENDING)
            ->orderBy('id')
            ->get();
    }
}
