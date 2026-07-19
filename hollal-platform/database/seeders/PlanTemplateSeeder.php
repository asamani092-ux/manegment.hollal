<?php

namespace Database\Seeders;

use App\Models\PlanTemplate;
use App\Models\TemplateItem;
use App\Services\PlanTemplateService;
use Illuminate\Database\Seeder;

/**
 * 06A-B2 — seed the two plan templates with their documented structure counts:
 * خطة حلل = 61 بندًا · خطة الجهة = 135 بندًا (matching the Excel plans).
 *
 * The titles here follow the Excel section headings; the exact wording, the day
 * offsets and the role assignments are corrected in the review session with
 * عبدالله — which is why both templates are seeded with `needs_review = true`
 * and generation stays blocked until that session signs them off.
 *
 * Idempotent: re-running does not duplicate templates or items.
 */
class PlanTemplateSeeder extends Seeder
{
    /** Documented structure of خطة حلل — 4+12+24+15+6 = 61 items. */
    private const HOLLAL_STRUCTURE = [
        'phases' => ['التهيئة والتخطيط', 'الإطلاق', 'التنفيذ', 'القياس والختام'],
        'per_phase' => 3,   // 12 main procedures
        'per_procedure' => 2, // 24 sub-procedures
        'level4' => 15,
        'level5' => 6,
        'total' => 61,
    ];

    /** Documented structure of خطة الجهة — 5+20+40+40+30 = 135 items. */
    private const ENTITY_STRUCTURE = [
        'phases' => ['التهيئة', 'التنسيق الداخلي', 'التنفيذ الميداني', 'المتابعة', 'الإغلاق والتسليم'],
        'per_phase' => 4,   // 20 main procedures
        'per_procedure' => 2, // 40 sub-procedures
        'level4' => 40,
        'level5' => 30,
        'total' => 135,
    ];

    /** @var list<string> roles used by the seeded items */
    private const HOLLAL_ROLES = ['مدير مشروع حلل', 'مشرف علمي', 'شارح', 'منسق'];

    private const ENTITY_ROLES = ['مدير جهة', 'منسق الجهة', 'معلم', 'مشرف الجهة'];

    public function run(): void
    {
        $this->seedTemplate(
            PlanTemplate::KIND_HOLLAL,
            'خطة حلل',
            self::HOLLAL_STRUCTURE,
            self::HOLLAL_ROLES,
        );

        $this->seedTemplate(
            PlanTemplate::KIND_ENTITY,
            'خطة الجهة',
            self::ENTITY_STRUCTURE,
            self::ENTITY_ROLES,
        );
    }

    /**
     * @param  array<string, mixed>  $structure
     * @param  list<string>  $roles
     */
    private function seedTemplate(string $kind, string $name, array $structure, array $roles): void
    {
        if (PlanTemplate::where('kind', $kind)->exists()) {
            return;
        }

        $service = app(PlanTemplateService::class);
        $template = $service->create($name, $kind);
        $version = $template->currentVersion;

        $offset = 0;
        $level3Items = [];
        $level4Items = [];

        foreach ($structure['phases'] as $phaseIndex => $phaseTitle) {
            $phase = $service->addItem($version, [
                'title' => $phaseTitle,
                'role' => $roles[0],
                'start_offset_days' => $offset,
                'duration_days' => 7,
                'evidence_required' => 'محضر المرحلة',
                'position' => $phaseIndex,
            ]);
            $offset += 7;

            for ($p = 0; $p < $structure['per_phase']; $p++) {
                $procedure = $service->addItem($version, [
                    'title' => $phaseTitle.' — إجراء رئيسي '.($p + 1),
                    'role' => $roles[($p + 1) % count($roles)],
                    'start_offset_days' => $offset + $p,
                    'duration_days' => 3,
                    'evidence_required' => 'تقرير الإجراء',
                    'position' => $p,
                ], $phase);

                for ($s = 0; $s < $structure['per_procedure']; $s++) {
                    $level3Items[] = $service->addItem($version, [
                        'title' => $phaseTitle.' — إجراء فرعي '.($p + 1).'.'.($s + 1),
                        'role' => $roles[($s + 2) % count($roles)],
                        'start_offset_days' => $offset + $p + $s,
                        'duration_days' => 2,
                        'evidence_required' => 'شاهد تنفيذ',
                        'position' => $s,
                    ], $procedure);
                }
            }
        }

        for ($i = 0; $i < $structure['level4']; $i++) {
            $parent = $level3Items[$i % count($level3Items)];
            $isService = $i % 5 === 4;

            $level4Items[] = $service->addItem($version, [
                'title' => 'مهمة تفصيلية رئيسية '.($i + 1),
                'role' => $roles[$i % count($roles)],
                'start_offset_days' => $parent->start_offset_days + 1,
                'duration_days' => 2,
                'evidence_required' => 'مرفق إثبات',
                'item_kind' => $isService ? TemplateItem::KIND_SERVICE : TemplateItem::KIND_MANDATORY,
                'service_type' => $isService ? ['تدريب', 'زيارة', 'استشارة', 'قياس'][$i % 4] : null,
                'position' => $i,
            ], $parent);
        }

        for ($i = 0; $i < $structure['level5']; $i++) {
            $parent = $level4Items[$i % count($level4Items)];

            $service->addItem($version, [
                'title' => 'مهمة تفصيلية فرعية '.($i + 1),
                'role' => $roles[$i % count($roles)],
                'start_offset_days' => $parent->start_offset_days + 1,
                'duration_days' => 1,
                'evidence_required' => 'صورة/مستند',
                'position' => $i,
            ], $parent);
        }
    }
}
