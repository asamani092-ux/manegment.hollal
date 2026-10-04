<?php

namespace Tests\Feature;

use App\Services\ViolationService;
use Tests\TestCase;

class ViolationBaselineTest extends TestCase
{
    public function test_harsher_company_penalty_requires_confirmation_and_lighter_does_not(): void
    {
        $service = app(ViolationService::class);
        $baseline = [['type' => 'warning', 'value' => 0]];
        $harsher = [['type' => 'deduct_days', 'value' => 2]];
        $lighter = [['type' => 'warning', 'value' => 0]];

        $this->assertTrue($service->isHarsherThanBaseline($harsher[0], $baseline[0]));

        try {
            $service->assertCompanyRevision($harsher, $baseline, 'تعديل');
            $this->fail('كان يجب رفض الجزاء الأشد');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('الجزاء أشد من النموذج المعتمد من الوزارة', $e->getMessage());
        }

        $service->assertCompanyRevision($harsher, $baseline, 'أؤكد أن الجزاء أشد');
        $service->assertCompanyRevision($lighter, $baseline, 'تعديل أخف');
        $this->assertFalse($service->isHarsherThanBaseline($lighter[0], $baseline[0]));
    }
}
