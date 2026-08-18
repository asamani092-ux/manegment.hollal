<?php

namespace Database\Seeders;

use App\Models\DiagnosisAnswer;
use App\Models\PartnerLink;
use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\PartnershipPayment;
use App\Models\PartnershipStageLog;
use App\Models\ProjectGenerationRequest;
use App\Models\Quote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reset every partnership to diagnosis readiness for UAT of the new cycle.
 * Time: O(n) | Space: O(1)
 */
class PartnershipReadinessSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('project_generation_requests')) {
                ProjectGenerationRequest::query()->forceDelete();
            }
            if (Schema::hasTable('partnership_payments')) {
                PartnershipPayment::query()->forceDelete();
            }
            if (Schema::hasTable('contract_payment_schedules')) {
                DB::table('contract_payment_schedules')->delete();
            }
            if (Schema::hasTable('partnership_contracts')) {
                PartnershipContract::query()->forceDelete();
            }
            if (Schema::hasTable('quote_items')) {
                DB::table('quote_items')->delete();
            }
            if (Schema::hasTable('quotes')) {
                Quote::query()->forceDelete();
            }
            if (Schema::hasTable('partner_link_activities')) {
                DB::table('partner_link_activities')->delete();
            }
            if (Schema::hasTable('partner_links')) {
                PartnerLink::query()->forceDelete();
            }
            if (Schema::hasTable('diagnosis_answers')) {
                DiagnosisAnswer::query()->delete();
            }
            if (Schema::hasTable('partnership_allowed_programs')) {
                DB::table('partnership_allowed_programs')->delete();
            }

            Partnership::withTrashed()->each(function (Partnership $partnership) {
                $from = (int) $partnership->stage;
                $partnership->forceFill([
                    'stage' => Partnership::STAGE_DIAGNOSIS,
                    'stage_entered_at' => now(),
                    'status' => 'active',
                    'awaiting_internal_approval' => false,
                    'internal_approval_notes' => null,
                    'portal_features' => Partnership::defaultPortalFeatures(),
                    'stalled_reason' => null,
                    'closed_reason' => null,
                    'deleted_at' => null,
                ])->save();

                PartnershipStageLog::query()->create([
                    'partnership_id' => $partnership->id,
                    'from_stage' => $from,
                    'to_stage' => Partnership::STAGE_DIAGNOSIS,
                    'note' => 'تجديد البيانات لمرحلة الجاهزية (التشخيص)',
                    'changed_by' => null,
                ]);
            });
        });

        $this->command?->info('كل الشراكات الآن في مرحلة التشخيص بدون عروض/روابط/عقود قديمة.');
    }
}
