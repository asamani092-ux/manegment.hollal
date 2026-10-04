<?php

namespace Tests\Feature;

use App\Models\EmployeeDocument;
use App\Models\EmployeeOnboardingItem;
use App\Models\User;
use App\Services\DocumentReviewService;
use App\Services\OnboardingChecklistService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrDocumentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_review_and_onboarding_actions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceListsSeeder::class);

        $employee = User::factory()->create();
        $hr = User::factory()->create();
        $hr->givePermissionTo('hr.documents.review');

        $doc = app(DocumentReviewService::class)->submit($employee, EmployeeDocument::TYPE_ID, 'ids/a.pdf');
        $this->assertSame('pending_review', $doc->status);
        $approved = app(DocumentReviewService::class)->approve($doc, $hr);
        $this->assertSame('approved', $approved->status);

        $next = app(DocumentReviewService::class)->submit($employee, EmployeeDocument::TYPE_ID, 'ids/b.pdf');
        app(DocumentReviewService::class)->approve($next, $hr);
        $this->assertSame('superseded', $doc->fresh()->status);

        $rejected = app(DocumentReviewService::class)->submit($employee, EmployeeDocument::TYPE_PASSPORT);
        app(DocumentReviewService::class)->reject($rejected, $hr, 'صورة غير واضحة');
        $this->assertSame('rejected', $rejected->fresh()->status);

        app(OnboardingChecklistService::class)->generate($employee);
        $item = EmployeeOnboardingItem::query()->where('user_id', $employee->id)->where('status', 'open')->first();
        $this->assertNotNull($item);
        app(OnboardingChecklistService::class)->convertToTask($item, $employee, $hr);
        $this->assertSame('converted_to_task', $item->fresh()->status);
        $this->assertNotNull($item->fresh()->task_id);

        $employee->forceFill(['manager_override_id' => $hr->id, 'manager_id' => null])->save();
        $this->assertSame($hr->id, $employee->fresh()->effectiveManager()?->id);
    }
}
