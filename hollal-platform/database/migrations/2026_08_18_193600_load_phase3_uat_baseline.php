<?php

use App\Models\UatToolChecklist;
use App\Services\UatToolChecklistService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('uat_tool_checklists')) {
            return;
        }

        $baseline = config('uat_tools.baseline', []);
        if ($baseline === []) {
            return;
        }

        app(UatToolChecklistService::class)->save(
            [
                'verdicts' => $baseline['verdicts'] ?? [],
                'tags' => $baseline['tags'] ?? [],
                'notes' => $baseline['notes'] ?? [],
                'activePhase' => 3,
            ],
            null,
            true,
            'baseline-phase3-2026-08-17',
        );
    }

    public function down(): void
    {
        UatToolChecklist::query()->where('slot', UatToolChecklist::SLOT_SHARED)->delete();
    }
};
