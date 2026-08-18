<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Partnership;
use App\Models\PartnershipStageLog;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new journey under an organization at مرحلة فرصة.
 * Catalog programs are attached; the quote is built later from ملف الشراكة.
 */
class PartnershipQuickCreateService
{
    /**
     * @param  list<int|string>  $programIds
     */
    public function create(Organization $organization, User $owner, array $programIds): Partnership
    {
        $programIds = collect($programIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($programIds === []) {
            throw new \RuntimeException('اختر برنامجًا واحدًا على الأقل من الكتالوج');
        }

        $programs = Program::query()
            ->where('stage', Program::STAGE_ACTIVE)
            ->whereIn('id', $programIds)
            ->get();

        if ($programs->count() !== count($programIds)) {
            throw new \RuntimeException('لا يمكن إضافة برنامج غير نشط إلى كتالوج الشراكة');
        }

        return DB::transaction(function () use ($organization, $owner, $programIds) {
            $partnership = Partnership::create([
                'organization_id' => $organization->id,
                'owner_id' => $owner->id,
                'entity_name' => $organization->name,
                'stage' => Partnership::STAGE_OPPORTUNITY,
                'stage_entered_at' => now(),
            ]);

            $partnership->allowedPrograms()->sync($programIds);

            PartnershipStageLog::create([
                'partnership_id' => $partnership->id,
                'from_stage' => null,
                'to_stage' => Partnership::STAGE_OPPORTUNITY,
                'note' => 'إنشاء رحلة شراكة من الجهات الشريكة',
                'changed_by' => $owner->id,
            ]);

            return $partnership->fresh(['allowedPrograms']);
        });
    }
}
