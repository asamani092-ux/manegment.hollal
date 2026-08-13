<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Partnership;
use App\Models\PartnershipStageLog;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates the self-serve starting point in one transaction:
 * organization + owner + allowed catalog + a priced draft quote.
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

        $prices = ProgramPrice::query()
            ->whereIn('program_id', $programIds)
            ->where('is_active', true)
            ->orderBy('program_id')
            ->orderBy('id')
            ->get();

        if ($prices->isEmpty()) {
            throw new \RuntimeException('لا توجد أسعار نشطة للبرامج المختارة');
        }

        return DB::transaction(function () use ($organization, $owner, $programIds, $prices) {
            $partnership = Partnership::create([
                'organization_id' => $organization->id,
                'owner_id' => $owner->id,
                'entity_name' => $organization->name,
                'stage' => Partnership::STAGE_QUOTE,
                'stage_entered_at' => now(),
            ]);

            $partnership->allowedPrograms()->sync($programIds);

            $quote = app(QuoteService::class)->create(
                $partnership,
                $prices->map(fn (ProgramPrice $price) => [
                    'program_id' => $price->program_id,
                    'service_type' => $price->service_type,
                    'quantity' => 1,
                    'unit_price' => (float) $price->unit_price,
                ])->all(),
                author: $owner,
            );

            $partnership->forceFill(['expected_value' => $quote->total])->save();

            PartnershipStageLog::create([
                'partnership_id' => $partnership->id,
                'from_stage' => null,
                'to_stage' => Partnership::STAGE_QUOTE,
                'note' => 'إنشاء شراكة من كتالوج البرامج',
                'changed_by' => $owner->id,
            ]);

            return $partnership->fresh(['allowedPrograms', 'quotes.items']);
        });
    }
}
