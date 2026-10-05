<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\PayrollRun;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\CompanyProfile;
use App\Models\Custody;
use App\Models\CustodySettlementItem;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\CustodyService;
use App\Services\ExpenseApprovalService;
use App\Services\JournalService;
use App\Services\RevenueService;
use App\Services\TaxInvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo finance data for UAT — assets, revenues, tax invoices, expense requests
 * and custodies on non-empty screens. Run manually:
 * php artisan db:seed --class=DemoFinanceSeeder
 *
 * Idempotent: every row is matched on a natural key before insert.
 * Time: O(n) inserts (n ≈ 30 rows) | Space: O(1) per row.
 */
class DemoFinanceSeeder extends Seeder
{
    private ?User $executive = null;

    private ?User $projectManager = null;

    private ?User $finance = null;

    private ?User $employee = null;

    public function run(): void
    {
        $this->executive = User::where('phone', '0502222222')->first();
        $this->projectManager = User::where('phone', '0503333333')->first();
        $this->finance = User::where('phone', '0504444444')->first();
        $this->employee = User::where('phone', '0505555555')->first();

        if (! $this->executive || ! $this->projectManager || ! $this->finance || ! $this->employee) {
            return;
        }

        $this->call(ApprovalRulesSeeder::class);

        $this->seedCompanyProfile();
        $this->seedPlaceholderEvidenceFiles();
        $this->seedAssets();
        $this->seedRevenues();
        $this->seedTaxInvoices();
        $this->seedExpenseRequests();
        $this->seedCustodies();
        $this->postLedgers();
    }

    private function seedCompanyProfile(): void
    {
        $profile = CompanyProfile::current();
        $profile->fill([
            'name' => $profile->name ?: 'مؤسسة حلّل',
            'tax_number' => $profile->tax_number ?: '300000000000003',
            'commercial_register' => $profile->commercial_register ?: '1010123456',
            'address' => $profile->address ?: 'الرياض — حي العليا — طريق الملك فهد',
        ]);
        $profile->save();
    }

    /** Placeholder private-disk files so financial documents index is not empty. */
    private function seedPlaceholderEvidenceFiles(): void
    {
        $disk = Storage::disk('local');
        foreach ([
            'revenues/demo-evidence-1.pdf',
            'revenues/demo-evidence-2.pdf',
            'expenses/official/demo-invoice-1.pdf',
            'expenses/official/demo-invoice-2.pdf',
            'custodies/settlements/demo-invoice.pdf',
            'custodies/disbursements/demo-proof.pdf',
            'payroll/demo-proof.pdf',
        ] as $path) {
            if (! $disk->exists($path)) {
                $disk->put($path, "%PDF-1.4\n% demo placeholder for {$path}\n");
            }
        }
    }

    /** 6 assets + handover/maintenance/retirement movements. */
    private function seedAssets(): void
    {
        $rows = [
            [
                'code' => 'AST-D001',
                'name_ar' => 'حاسب محمول لينوفو ThinkPad',
                'category' => 'أجهزة حاسب',
                'purchase_date' => now()->subMonths(8)->startOfMonth()->toDateString(),
                'purchase_amount' => 4800.00,
                'location' => 'مقر المؤسسة — الدور الثاني',
                'condition' => Asset::CONDITION_GOOD,
                'holder' => $this->employee,
                'holder_since' => now()->subMonths(6)->toDateString(),
            ],
            [
                'code' => 'AST-D002',
                'name_ar' => 'حاسب مكتبي HP ProDesk',
                'category' => 'أجهزة حاسب',
                'purchase_date' => now()->subMonths(14)->startOfMonth()->toDateString(),
                'purchase_amount' => 3200.00,
                'location' => 'قسم المالية والحسابات',
                'condition' => Asset::CONDITION_MAINTENANCE,
                'holder' => null,
                'holder_since' => null,
            ],
            [
                'code' => 'AST-D003',
                'name_ar' => 'جهاز عرض إبسون EB-X06',
                'category' => 'أجهزة عرض',
                'purchase_date' => now()->subMonths(5)->startOfMonth()->toDateString(),
                'purchase_amount' => 2150.00,
                'location' => 'قاعة الاجتماعات الرئيسية',
                'condition' => Asset::CONDITION_GOOD,
                'holder' => $this->projectManager,
                'holder_since' => now()->subMonths(3)->toDateString(),
            ],
            [
                'code' => 'AST-D004',
                'name_ar' => 'مكتب إداري خشبي مع كرسي',
                'category' => 'أثاث مكتبي',
                'purchase_date' => now()->subMonths(20)->startOfMonth()->toDateString(),
                'purchase_amount' => 1750.00,
                'location' => 'مقر المؤسسة — الدور الأول',
                'condition' => Asset::CONDITION_GOOD,
                'holder' => null,
                'holder_since' => null,
            ],
            [
                'code' => 'AST-D005',
                'name_ar' => 'سيارة تويوتا هايس لنقل المستفيدين',
                'category' => 'مركبات',
                'purchase_date' => now()->subMonths(26)->startOfMonth()->toDateString(),
                'purchase_amount' => 96000.00,
                'location' => 'موقف المؤسسة',
                'condition' => Asset::CONDITION_GOOD,
                'holder' => null,
                'holder_since' => null,
            ],
            [
                'code' => 'AST-D006',
                'name_ar' => 'طابعة ليزر قديمة سامسونج',
                'category' => 'أخرى',
                'purchase_date' => now()->subMonths(40)->startOfMonth()->toDateString(),
                'purchase_amount' => 1200.00,
                'location' => 'المستودع',
                'condition' => Asset::CONDITION_RETIRED,
                'holder' => null,
                'holder_since' => null,
            ],
        ];

        /** @var array<string, Asset> $assets */
        $assets = [];

        foreach ($rows as $row) {
            $category = AssetCategory::where('name_ar', $row['category'])->first();

            $assets[$row['code']] = Asset::firstOrCreate(
                ['code' => $row['code']],
                [
                    'name_ar' => $row['name_ar'],
                    'category_id' => $category?->id,
                    'can_be_custody' => (bool) ($category?->can_be_custody ?? false),
                    'purchase_date' => $row['purchase_date'],
                    'purchase_amount' => $row['purchase_amount'],
                    'location' => $row['location'],
                    'condition' => $row['condition'],
                    'current_holder_id' => $row['holder']?->id,
                    'holder_since' => $row['holder_since'],
                ],
            );
            if ($assets[$row['code']]->wasRecentlyCreated) {
                app(JournalService::class)->postAssetPurchased($assets[$row['code']]->fresh('category'), $this->finance);
            }
        }

        $this->addMovement($assets['AST-D001'], 'تسليم', [
            'to_holder_id' => $this->employee->id,
            'moved_at' => now()->subMonths(6),
            'reason' => 'تسليم عهدة جهاز للموظف بعد المباشرة',
        ]);

        $this->addMovement($assets['AST-D003'], 'تسليم', [
            'to_holder_id' => $this->projectManager->id,
            'moved_at' => now()->subMonths(3),
            'reason' => 'تسليم جهاز العرض لمدير المشاريع لتنفيذ ورش المستفيدين',
        ]);

        $this->addMovement($assets['AST-D002'], 'صيانة', [
            'from_holder_id' => $this->finance->id,
            'moved_at' => now()->subDays(21),
            'reason' => 'إرسال الجهاز لصيانة القرص الصلب',
        ]);

        $this->addMovement($assets['AST-D006'], 'استبعاد', [
            'moved_at' => now()->subDays(45),
            'reason' => 'استبعاد الطابعة لانتهاء عمرها التشغيلي',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function addMovement(Asset $asset, string $movementType, array $attributes): void
    {
        AssetMovement::firstOrCreate(
            [
                'asset_id' => $asset->id,
                'movement_type' => $movementType,
                'reason' => $attributes['reason'],
            ],
            $attributes,
        );
    }

    /** 6 revenues spread over 3 months across the seeded revenue categories. */
    private function seedRevenues(): void
    {
        $rows = [
            [
                'source_type' => Revenue::SOURCE_PARTNERSHIP,
                'category' => 'شراكات',
                'amount' => 150000.00,
                'received_at' => now()->subMonths(2)->startOfMonth()->addDays(4)->toDateString(),
                'status' => Revenue::STATUS_CONFIRMED,
                'evidence' => 'revenues/demo-evidence-1.pdf',
            ],
            [
                'source_type' => Revenue::SOURCE_MANUAL,
                'category' => 'تبرعات',
                'amount' => 25000.00,
                'received_at' => now()->subMonths(2)->startOfMonth()->addDays(18)->toDateString(),
                'status' => Revenue::STATUS_CONFIRMED,
                'evidence' => 'revenues/demo-evidence-2.pdf',
            ],
            [
                'source_type' => Revenue::SOURCE_MANUAL,
                'category' => 'منح',
                'amount' => 87500.00,
                'received_at' => now()->subMonth()->startOfMonth()->addDays(6)->toDateString(),
                'status' => Revenue::STATUS_CONFIRMED,
                'evidence' => 'revenues/demo-evidence-1.pdf',
            ],
            [
                'source_type' => Revenue::SOURCE_PARTNERSHIP,
                'category' => 'شراكات',
                'amount' => 62000.00,
                'received_at' => now()->subMonth()->startOfMonth()->addDays(20)->toDateString(),
                'status' => Revenue::STATUS_RECORDED,
            ],
            [
                'source_type' => Revenue::SOURCE_MANUAL,
                'category' => 'تبرعات',
                'amount' => 9800.00,
                'received_at' => now()->startOfMonth()->toDateString(),
                'status' => Revenue::STATUS_RECORDED,
            ],
            [
                'source_type' => Revenue::SOURCE_MANUAL,
                'category' => 'أخرى',
                'amount' => 4300.00,
                'received_at' => now()->toDateString(),
                'status' => Revenue::STATUS_RECORDED,
            ],
        ];

        foreach ($rows as $row) {
            // received_at is stored as a datetime string, so the date part is
            // matched with whereDate instead of a firstOrCreate equality key.
            $exists = Revenue::query()
                ->where('source_type', $row['source_type'])
                ->where('amount', $row['amount'])
                ->whereDate('received_at', $row['received_at'])
                ->exists();

            if ($exists) {
                continue;
            }

            $category = RevenueCategory::where('name_ar', $row['category'])->first();
            $confirmed = $row['status'] === Revenue::STATUS_CONFIRMED;

            if ($row['source_type'] === Revenue::SOURCE_MANUAL && $category) {
                $revenue = app(RevenueService::class)->recordManual(
                    (float) $row['amount'],
                    $category->id,
                    $row['received_at'],
                    $row['evidence'] ?? null,
                );
                if ($confirmed) {
                    $revenue->update([
                        'status' => Revenue::STATUS_CONFIRMED,
                        'confirmed_at' => $row['received_at'].' 10:00:00',
                        'confirmed_by' => $this->finance->id,
                    ]);
                }

                continue;
            }

            $revenue = Revenue::create([
                'source_type' => $row['source_type'],
                'amount' => $row['amount'],
                'received_at' => $row['received_at'],
                'category_id' => $category?->id,
                'status' => $row['status'],
                'external_document_path' => $row['evidence'] ?? null,
                'confirmed_at' => $confirmed ? $row['received_at'].' 10:00:00' : null,
                'confirmed_by' => $confirmed ? $this->finance->id : null,
            ]);
            if ($confirmed) {
                app(JournalService::class)->postRevenueConfirmed($revenue->fresh(['category.account']), $this->finance);
            }
        }
    }

    /**
     * 3 tax invoices issued through the service so the sequence, the 15% VAT
     * totals and the TLV QR payload stay consistent with production behaviour.
     * The demo source ids double as the idempotency key.
     */
    private function seedTaxInvoices(): void
    {
        $service = app(TaxInvoiceService::class);

        $rows = [
            [
                'source_id' => 940001,
                'invoice_type' => TaxInvoice::TYPE_STANDARD,
                'buyer' => ['name' => 'شركة الأفق للتنمية المجتمعية', 'vat_number' => '310000000000003'],
                'items' => [
                    ['description' => 'رسوم إدارة برنامج التأهيل المهني', 'quantity' => 1, 'unit_price' => 45000],
                    ['description' => 'إعداد التقارير الربعية للبرنامج', 'quantity' => 2, 'unit_price' => 3500],
                ],
            ],
            [
                'source_id' => 940002,
                'invoice_type' => TaxInvoice::TYPE_STANDARD,
                'buyer' => ['name' => 'مؤسسة نماء الخيرية', 'vat_number' => '311000000000003'],
                'items' => [
                    ['description' => 'استشارات بناء الخطة التشغيلية', 'quantity' => 1, 'unit_price' => 28000],
                ],
            ],
            [
                'source_id' => 940003,
                'invoice_type' => TaxInvoice::TYPE_SIMPLIFIED,
                'buyer' => ['name' => 'عبدالله بن سعد القحطاني', 'vat_number' => null],
                'items' => [
                    ['description' => 'رسوم اشتراك ورشة تدريبية', 'quantity' => 3, 'unit_price' => 750],
                ],
            ],
        ];

        foreach ($rows as $row) {
            $exists = TaxInvoice::query()
                ->where('source_type', TaxInvoice::SOURCE_MANUAL)
                ->where('source_id', $row['source_id'])
                ->exists();

            if ($exists) {
                continue;
            }

            $service->issue(
                items: $row['items'],
                buyer: $row['buyer'],
                issuer: $this->finance,
                sourceType: TaxInvoice::SOURCE_MANUAL,
                sourceId: $row['source_id'],
                invoiceType: $row['invoice_type'],
            );
        }
    }

    /** 5 expense requests covering pending / approved / paid / rejected. */
    private function seedExpenseRequests(): void
    {
        $rows = [
            [
                'requester' => $this->employee,
                'category' => 'مستلزمات مكتبية',
                'type' => 'supplies',
                'amount' => 2450.00,
                'reason' => 'شراء مستلزمات مكتبية لقسم المشاريع — الربع الحالي',
                'priority' => 'normal',
                'payment_method' => 'transfer',
                'status' => 'pending',
                'current_approval_stage' => ExpenseApprovalService::STAGE_EXECUTIVE,
                'logs' => [],
            ],
            [
                'requester' => $this->projectManager,
                'category' => 'مواصلات',
                'type' => 'travel',
                'amount' => 3800.00,
                'reason' => 'تذاكر وإقامة لزيارة ميدانية لمستفيدي برنامج الأسر المنتجة',
                'priority' => 'high',
                'payment_method' => 'transfer',
                'status' => 'pending',
                'current_approval_stage' => ExpenseApprovalService::STAGE_FINANCE,
                'approver' => $this->executive,
                'approved_at' => now()->subDays(2),
                'logs' => [
                    ['stage' => ExpenseApprovalService::STAGE_EXECUTIVE, 'approver' => $this->executive, 'action' => 'approved', 'notes' => 'معتمد ضمن خطة الزيارات الميدانية', 'acted_at' => now()->subDays(2)],
                ],
            ],
            [
                'requester' => $this->employee,
                'category' => 'ضيافة',
                'type' => 'operational',
                'amount' => 1650.00,
                'reason' => 'ضيافة اجتماع الشركاء الاستراتيجيين',
                'priority' => 'normal',
                'payment_method' => 'pos',
                'status' => 'approved',
                'current_approval_stage' => null,
                'approver' => $this->finance,
                'approved_at' => now()->subDays(6),
                'paid_ready_at' => now()->subDays(6),
                'logs' => [
                    ['stage' => ExpenseApprovalService::STAGE_EXECUTIVE, 'approver' => $this->executive, 'action' => 'approved', 'notes' => null, 'acted_at' => now()->subDays(7)],
                    ['stage' => ExpenseApprovalService::STAGE_FINANCE, 'approver' => $this->finance, 'action' => 'approved', 'notes' => 'جاهز للصرف', 'acted_at' => now()->subDays(6)],
                ],
            ],
            [
                'requester' => $this->projectManager,
                'category' => 'تدريب وتطوير',
                'type' => 'operational',
                'amount' => 12500.00,
                'reason' => 'رسوم برنامج تدريبي لبناء قدرات فريق المشاريع',
                'priority' => 'high',
                'payment_method' => 'transfer',
                'status' => 'paid',
                'current_approval_stage' => null,
                'approver' => $this->finance,
                'approved_at' => now()->subDays(18),
                'paid_ready_at' => now()->subDays(18),
                'logs' => [
                    ['stage' => ExpenseApprovalService::STAGE_EXECUTIVE, 'approver' => $this->executive, 'action' => 'approved', 'notes' => null, 'acted_at' => now()->subDays(20)],
                    ['stage' => ExpenseApprovalService::STAGE_FINANCE, 'approver' => $this->finance, 'action' => 'approved', 'notes' => 'تم التحويل البنكي', 'acted_at' => now()->subDays(18)],
                ],
            ],
            [
                'requester' => $this->employee,
                'category' => 'أخرى',
                'type' => 'other',
                'amount' => 7400.00,
                'reason' => 'شراء أجهزة إضافية خارج خطة المشتريات المعتمدة',
                'priority' => 'low',
                'payment_method' => 'other',
                'status' => 'rejected',
                'current_approval_stage' => null,
                'approver' => $this->executive,
                'approved_at' => now()->subDays(11),
                'rejection_reason' => 'الطلب خارج الموازنة المعتمدة للربع الحالي — يُعاد تقديمه في الربع القادم',
                'logs' => [
                    ['stage' => ExpenseApprovalService::STAGE_EXECUTIVE, 'approver' => $this->executive, 'action' => 'rejected', 'notes' => 'خارج الموازنة المعتمدة', 'acted_at' => now()->subDays(11)],
                ],
            ],
        ];

        foreach ($rows as $row) {
            if (ExpenseRequest::query()->where('reason', $row['reason'])->exists()) {
                continue;
            }
            $category = ExpenseCategory::where('name_ar', $row['category'])->first();
            $expense = ExpenseRequest::create([
                'requester_id' => $row['requester']->id,
                'category_id' => $category?->id,
                'type' => $row['type'],
                'amount' => $row['amount'],
                'reason' => $row['reason'],
                'priority' => $row['priority'],
                'payment_method' => $row['payment_method'],
                'status' => 'draft',
                'official_document_path' => in_array($row['status'], ['approved', 'paid'], true)
                    ? 'expenses/official/demo-invoice-1.pdf'
                    : null,
            ]);
            $this->driveExpense($expense, $row);
        }
    }

    /** @param  array<string, mixed>  $row */
    private function driveExpense(ExpenseRequest $expense, array $row): void
    {
        $service = app(ExpenseApprovalService::class);
        try {
            $service->initializeChain($expense);
            $guard = 0;
            while ($expense->fresh()->status === 'pending' && $guard < 8) {
                $expense = $expense->fresh();
                if ($row['status'] === 'rejected') {
                    $service->reject($this->executive, $expense, (string) ($row['rejection_reason'] ?? 'مرفوض'));

                    return;
                }
                if ($row['status'] === 'pending') {
                    return;
                }
                $approver = $service->approversForStage($expense, (string) $expense->current_approval_stage)->first();
                if (! $approver) {
                    break;
                }
                $service->approve($approver, $expense);
                $guard++;
            }
        } catch (\Throwable) {
            $expense->update(['status' => $row['status'] === 'paid' ? 'approved' : $row['status']]);
        }

        $expense = $expense->fresh();
        if ($row['status'] === 'paid') {
            if ($expense->status !== 'paid') {
                $expense->update(['status' => 'paid', 'paid_ready_at' => $expense->paid_ready_at ?? now()]);
            }
            app(JournalService::class)->postExpensePaid($expense->fresh(['category.account']), $this->finance);
        }
    }

    /** 4 custodies covering طلب / معتمدة / صرف / تسوية (+ settlement items). */
    private function seedCustodies(): void
    {
        $rows = [
            [
                'purpose' => 'مصروفات نثرية لتجهيز معرض الأسر المنتجة',
                'employee' => $this->employee,
                'category' => 'ضيافة',
                'amount' => 2000.00,
                'status' => Custody::STATUS_REQUESTED,
                'due_date' => now()->addDays(30)->toDateString(),
            ],
            [
                'purpose' => 'عهدة تنفيذ زيارات ميدانية لمستفيدي برنامج التأهيل',
                'employee' => $this->projectManager,
                'category' => 'مواصلات',
                'amount' => 4500.00,
                'status' => Custody::STATUS_APPROVED,
                'approved_by' => $this->executive,
                'due_date' => now()->addDays(21)->toDateString(),
            ],
            [
                'purpose' => 'عهدة شراء مستلزمات ورشة تدريبية في الرياض',
                'employee' => $this->employee,
                'category' => 'مستلزمات مكتبية',
                'amount' => 5000.00,
                'disbursed_amount' => 5000.00,
                'status' => Custody::STATUS_DISBURSED,
                'approved_by' => $this->executive,
                'due_date' => now()->addDays(14)->toDateString(),
            ],
            [
                'purpose' => 'عهدة تشغيل الملتقى السنوي للمتطوعين',
                'employee' => $this->projectManager,
                'category' => 'ضيافة',
                'amount' => 3000.00,
                'disbursed_amount' => 3000.00,
                'status' => Custody::STATUS_SETTLING,
                'approved_by' => $this->executive,
                'due_date' => now()->subDays(5)->toDateString(),
                'items' => [
                    ['description' => 'وجبات وضيافة المشاركين', 'amount' => 1800.00, 'category' => 'ضيافة'],
                    ['description' => 'نقل المتطوعين من وإلى مقر الملتقى', 'amount' => 1200.00, 'category' => 'مواصلات'],
                ],
            ],
        ];

        $service = app(CustodyService::class);
        foreach ($rows as $row) {
            if (Custody::query()->where('purpose', $row['purpose'])->exists()) {
                continue;
            }
            $category = ExpenseCategory::where('name_ar', $row['category'])->first();
            $custody = $service->request(
                $row['employee'],
                (float) $row['amount'],
                $row['purpose'],
                $category?->id,
                null,
                $row['due_date'],
                $row['employee'],
            );
            $this->driveCustody($service, $custody, $row);

            foreach ($row['items'] ?? [] as $item) {
                $itemCategory = ExpenseCategory::where('name_ar', $item['category'])->first();

                CustodySettlementItem::firstOrCreate(
                    [
                        'custody_id' => $custody->id,
                        'description' => $item['description'],
                    ],
                    [
                        'amount' => $item['amount'],
                        'category_id' => $itemCategory?->id,
                        'invoice_file' => 'custodies/settlements/demo-invoice.pdf',
                    ],
                );
            }
        }
    }

    /** @param  array<string, mixed>  $row */
    private function driveCustody(CustodyService $service, Custody $custody, array $row): void
    {
        $target = $row['status'];
        if ($target === Custody::STATUS_REQUESTED) {
            return;
        }
        $guard = 0;
        while ($custody->fresh()->status === Custody::STATUS_REQUESTED && $guard < 6) {
            $custody = $custody->fresh();
            $steps = app(\App\Services\ApprovalChainService::class)->stepsFor('custody', (float) $custody->amount);
            $request = \App\Models\ApprovalRequest::query()
                ->where('approvable_type', 'custody')
                ->where('approvable_id', $custody->id)
                ->first();
            $pending = $request?->steps()->where('status', 'pending')->orderBy('step_index')->first();
            $stage = $pending
                ? (string) ($pending->definition['legacy_stage'] ?? '')
                : (string) ($steps[0] ?? '');
            $employee = $custody->employee ?? User::query()->find($custody->employee_id);
            if ($stage === ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER && $employee && ! $employee->effectiveManager()) {
                $employee->forceFill(['manager_id' => $this->executive->id])->save();
            }
            $actor = $employee ? $this->actorForStage($stage, $employee) : $this->finance;
            if (! $actor) {
                break;
            }
            try {
                $custody = $service->approve($custody, $actor);
            } catch (\Throwable) {
                break;
            }
            $guard++;
        }
        $custody = $custody->fresh();
        if (in_array($target, [Custody::STATUS_DISBURSED, Custody::STATUS_SETTLING, Custody::STATUS_CLOSED], true)
            && $custody->status === Custody::STATUS_APPROVED) {
            $service->disburse($custody, 'custodies/disbursements/demo-proof.pdf');
        }
    }

    private function actorForStage(string $stage, User $employee): ?User
    {
        if ($stage === '' ) {
            return $this->finance;
        }
        if ($stage === \App\Services\ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER) {
            return $employee->effectiveManager() ?? $this->executive;
        }
        if (str_starts_with($stage, 'user:')) {
            return User::query()->find((int) substr($stage, 5));
        }
        if (str_starts_with($stage, 'users:')) {
            $id = (int) explode(',', substr($stage, 6))[0];

            return User::query()->find($id) ?? $this->finance;
        }

        return $this->finance;
    }

    private function postLedgers(): void
    {
        $journal = app(JournalService::class);
        foreach (ExpenseRequest::query()->where('status', 'paid')->get() as $expense) {
            $journal->postExpensePaid($expense->fresh(['category.account']), $this->finance);
        }
        foreach (Custody::query()->whereIn('status', [Custody::STATUS_DISBURSED, Custody::STATUS_SETTLING, Custody::STATUS_CLOSED])->get() as $custody) {
            $journal->postCustodyDisbursed($custody, $this->finance);
        }
        foreach (Revenue::query()->where('status', Revenue::STATUS_CONFIRMED)->get() as $revenue) {
            $journal->postRevenueConfirmed($revenue->fresh(['category.account']), $this->finance);
        }
        foreach (Asset::query()->where('purchase_amount', '>', 0)->get() as $asset) {
            $journal->postAssetPurchased($asset->fresh('category'), $this->finance);
        }
        foreach (PayrollRun::query()->where('status', PayrollRun::STATUS_EXECUTED)->get() as $run) {
            $journal->postPayrollExecuted($run, $this->finance);
        }
    }
}
