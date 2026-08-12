<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Contract;
use App\Models\EmployeeProfile;
use App\Models\EvaluationScore;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PayScale;
use App\Models\PeriodicEvaluation;
use App\Models\Responsibility;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * بيانات تجريبية لشاشات الموارد البشرية (UAT) — عربية وواقعية بمبالغ بالريال.
 * كل قسم يستخدم firstOrCreate على مفتاح طبيعي، فتكرار التشغيل لا يضاعف الصفوف.
 *
 * Time: O(n) حيث n = عدد المستخدمين + عدد الصفوف الثابتة | Space: O(n).
 */
class DemoHrSeeder extends Seeder
{
    private const PHONE_ADMIN = '0500000000';

    private const PHONE_GM = '0501111111';

    private const PHONE_EXECUTIVE = '0502222222';

    private const PHONE_PROJECTS = '0503333333';

    private const PHONE_FINANCE = '0504444444';

    private const PHONE_EMPLOYEE = '0505555555';

    /** @var array<string, User> */
    private array $users = [];

    public function run(): void
    {
        $this->users = User::query()
            ->whereIn('phone', [
                self::PHONE_ADMIN, self::PHONE_GM, self::PHONE_EXECUTIVE,
                self::PHONE_PROJECTS, self::PHONE_FINANCE, self::PHONE_EMPLOYEE,
            ])
            ->get()
            ->keyBy('phone')
            ->all();

        // بدون مستخدمي العرض لا معنى للبيانات — نخرج بدل الانهيار.
        if (! $this->user(self::PHONE_ADMIN) || ! $this->user(self::PHONE_EMPLOYEE)) {
            return;
        }

        $this->seedProfiles();
        $this->seedContracts();
        $this->seedPayScalesAndComponents();
        $this->seedPayrolls();
        $this->seedPayrollRuns();
        $this->seedResponsibilities();
        $this->seedEvaluations();
        $this->seedAttendance();
        $this->seedLeaveRequests();
    }

    private function user(string $phone): ?User
    {
        return $this->users[$phone] ?? null;
    }

    /**
     * ملف وظيفي لكل مستخدم قائم. الرصيد السنوي يساوي 21 يومًا ناقص أيام
     * الإجازات السنوية المعتمدة التي يزرعها هذا الملف (3 أيام للمدير فقط).
     */
    private function seedProfiles(): void
    {
        $byPhone = [
            self::PHONE_ADMIN => [
                'job_title' => 'مدير النظام',
                'employment_type' => 'دوام_كامل',
                'hire_date' => '2023-01-15',
                'gender' => 'ذكر',
                'marital_status' => 'متزوج',
                'weekly_hours' => 40,
                'overtime_hour_value' => 75,
                'overtime_unlocked' => true,
                'annual_leave_balance' => 18,
                'address' => 'الرياض — حي النرجس',
                'emergency_contact_name' => 'عبدالله الحربي',
                'emergency_contact_phone' => '0551110000',
                'notes' => 'حساب إداري للمنصة.',
            ],
            self::PHONE_GM => [
                'job_title' => 'المدير العام',
                'employment_type' => 'دوام_كامل',
                'hire_date' => '2022-03-01',
                'gender' => 'ذكر',
                'marital_status' => 'متزوج',
                'weekly_hours' => 40,
                'overtime_hour_value' => 0,
                'overtime_unlocked' => false,
                'annual_leave_balance' => 21,
                'address' => 'الرياض — حي الملقا',
                'emergency_contact_name' => 'منى القحطاني',
                'emergency_contact_phone' => '0552221111',
                'notes' => 'يعتمد الخطط التشغيلية والمسيّرات.',
            ],
            self::PHONE_EXECUTIVE => [
                'job_title' => 'المديرة التنفيذية',
                'employment_type' => 'دوام_كامل',
                'hire_date' => '2022-09-11',
                'gender' => 'أنثى',
                'marital_status' => 'متزوجة',
                'weekly_hours' => 40,
                'overtime_hour_value' => 0,
                'overtime_unlocked' => false,
                'annual_leave_balance' => 21,
                'address' => 'الرياض — حي الياسمين',
                'emergency_contact_name' => 'فيصل الدوسري',
                'emergency_contact_phone' => '0553332222',
                'notes' => 'مسؤولة عن متابعة البرامج والشراكات.',
            ],
            self::PHONE_PROJECTS => [
                'job_title' => 'مدير المشاريع',
                'employment_type' => 'دوام_كامل',
                'hire_date' => '2024-02-04',
                'gender' => 'ذكر',
                'marital_status' => 'أعزب',
                'weekly_hours' => 40,
                'overtime_hour_value' => 55,
                'overtime_unlocked' => true,
                'annual_leave_balance' => 21,
                'address' => 'الرياض — حي العارض',
                'emergency_contact_name' => 'سعد الشمري',
                'emergency_contact_phone' => '0554443333',
                'notes' => 'يشرف على تنفيذ مشاريع الجمعية الميدانية.',
            ],
            self::PHONE_FINANCE => [
                'job_title' => 'محاسبة أولى',
                'employment_type' => 'دوام_كامل',
                'hire_date' => '2023-06-18',
                'gender' => 'أنثى',
                'marital_status' => 'عزباء',
                'weekly_hours' => 40,
                'overtime_hour_value' => 45,
                'overtime_unlocked' => false,
                'annual_leave_balance' => 21,
                'address' => 'الرياض — حي القيروان',
                'emergency_contact_name' => 'هند العتيبي',
                'emergency_contact_phone' => '0555554444',
                'notes' => 'مسؤولة عن الصرف والتحويلات البنكية.',
            ],
            self::PHONE_EMPLOYEE => [
                'job_title' => 'أخصائي برامج',
                'employment_type' => 'دوام_جزئي',
                'hire_date' => '2025-01-05',
                'gender' => 'ذكر',
                'marital_status' => 'أعزب',
                'weekly_hours' => 24,
                'overtime_hour_value' => 40,
                'overtime_unlocked' => true,
                'annual_leave_balance' => 21,
                'address' => 'الرياض — حي السويدي',
                'emergency_contact_name' => 'ريم الزهراني',
                'emergency_contact_phone' => '0556665555',
                'notes' => 'يشارك في تنفيذ الأنشطة التوعوية.',
            ],
        ];

        User::query()->select(['id', 'name', 'phone'])->chunkById(100, function ($chunk) use ($byPhone) {
            foreach ($chunk as $user) {
                EmployeeProfile::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    $byPhone[$user->phone] ?? [
                        'job_title' => $user->name,
                        'employment_type' => 'متعاون',
                        'hire_date' => now()->subYear()->toDateString(),
                        'weekly_hours' => 20,
                        'overtime_hour_value' => 0,
                        'overtime_unlocked' => false,
                        'annual_leave_balance' => 21,
                        'notes' => 'ملف وظيفي تجريبي.',
                    ]
                );
            }
        });
    }

    /** عقود: سارية، وشيك الانتهاء، ومنتهٍ. */
    private function seedContracts(): void
    {
        $rows = [
            [self::PHONE_GM, now()->subYears(2)->startOfMonth(), now()->addYear()->endOfMonth(), 26000, 'active'],
            [self::PHONE_EXECUTIVE, now()->subMonths(18)->startOfMonth(), now()->addMonths(8)->endOfMonth(), 22000, 'active'],
            [self::PHONE_PROJECTS, now()->subMonths(11)->startOfMonth(), now()->addDays(25), 16000, 'active'],
            [self::PHONE_FINANCE, now()->subMonths(14)->startOfMonth(), now()->subDays(40), 12000, 'expired'],
            [self::PHONE_EMPLOYEE, now()->subMonths(6)->startOfMonth(), now()->addMonths(6)->endOfMonth(), 9000, 'active'],
        ];

        foreach ($rows as [$phone, $start, $end, $value, $status]) {
            $employee = $this->user($phone);
            if (! $employee) {
                continue;
            }

            $exists = Contract::query()
                ->where('employee_id', $employee->id)
                ->whereDate('start_date', $start->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            Contract::query()->create([
                'employee_id' => $employee->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'value' => $value,
                'contract_file' => null,
                'status' => $status,
            ]);
        }
    }

    /** سلم رواتب سارٍ (3 درجات) + سلم سابق موقوف، ومكوّنات راتب لكل موظف. */
    private function seedPayScalesAndComponents(): void
    {
        PayScale::query()->firstOrCreate(
            ['name_ar' => 'سلم رواتب الجمعية 1447 هـ'],
            [
                'is_active' => true,
                'grades' => [
                    ['label' => 'الدرجة الأولى', 'base_amount' => 8000.0],
                    ['label' => 'الدرجة الثانية', 'base_amount' => 12000.0],
                    ['label' => 'الدرجة الثالثة', 'base_amount' => 18000.0],
                ],
            ]
        );

        PayScale::query()->firstOrCreate(
            ['name_ar' => 'سلم رواتب الجمعية 1446 هـ (موقوف)'],
            [
                'is_active' => false,
                'grades' => [
                    ['label' => 'الدرجة الأولى', 'base_amount' => 7500.0],
                    ['label' => 'الدرجة الثانية', 'base_amount' => 11000.0],
                    ['label' => 'الدرجة الثالثة', 'base_amount' => 16500.0],
                ],
            ]
        );

        $validFrom = now()->startOfYear()->toDateString();

        $rows = [
            [self::PHONE_GM, SalaryComponent::TYPE_BASE, 'الراتب الأساسي — الدرجة الثالثة', 18000],
            [self::PHONE_GM, SalaryComponent::TYPE_ALLOWANCE, 'بدل سكن', 3000],
            [self::PHONE_EXECUTIVE, SalaryComponent::TYPE_BASE, 'الراتب الأساسي — الدرجة الثالثة', 18000],
            [self::PHONE_EXECUTIVE, SalaryComponent::TYPE_ALLOWANCE, 'بدل نقل', 1200],
            [self::PHONE_PROJECTS, SalaryComponent::TYPE_BASE, 'الراتب الأساسي — الدرجة الثانية', 12000],
            [self::PHONE_PROJECTS, SalaryComponent::TYPE_ALLOWANCE, 'بدل نقل', 900],
            [self::PHONE_PROJECTS, SalaryComponent::TYPE_DEDUCTION, 'حصة الموظف — التأمينات الاجتماعية', 1170],
            [self::PHONE_FINANCE, SalaryComponent::TYPE_BASE, 'الراتب الأساسي — الدرجة الثانية', 12000],
            [self::PHONE_FINANCE, SalaryComponent::TYPE_DEDUCTION, 'حصة الموظف — التأمينات الاجتماعية', 1170],
            [self::PHONE_EMPLOYEE, SalaryComponent::TYPE_BASE, 'الراتب الأساسي — الدرجة الأولى', 8000],
            [self::PHONE_EMPLOYEE, SalaryComponent::TYPE_ALLOWANCE, 'بدل مواصلات', 600],
        ];

        foreach ($rows as [$phone, $type, $label, $amount]) {
            $employee = $this->user($phone);
            if (! $employee) {
                continue;
            }

            SalaryComponent::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'type' => $type, 'label_ar' => $label],
                [
                    'amount' => $amount,
                    'valid_from' => $validFrom,
                    'valid_to' => null,
                    'is_active' => true,
                ]
            );
        }
    }

    /** 12 صف راتب على ثلاثة أشهر لاختبار فلتر الشهر والترقيم. */
    private function seedPayrolls(): void
    {
        $plan = [
            self::PHONE_GM => [18000, 3000, 0],
            self::PHONE_EXECUTIVE => [18000, 1200, 0],
            self::PHONE_PROJECTS => [12000, 900, 1170],
            self::PHONE_EMPLOYEE => [8000, 600, 0],
        ];

        foreach ([2, 1, 0] as $monthsAgo) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $status = $monthsAgo === 0 ? 'pending' : 'transferred';

            foreach ($plan as $phone => [$base, $additions, $deductions]) {
                $employee = $this->user($phone);
                if (! $employee) {
                    continue;
                }

                $exists = Payroll::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('month', $month->toDateString())
                    ->exists();

                if ($exists) {
                    continue;
                }

                Payroll::query()->create([
                    'employee_id' => $employee->id,
                    'month' => $month->toDateString(),
                    'base' => $base,
                    'additions' => $additions,
                    'deductions' => $deductions,
                    'net' => Payroll::computeNet($base, $additions, $deductions),
                    'transfer_status' => $status,
                ]);
            }
        }
    }

    /**
     * ثلاثة مسيّرات في حالات مختلفة على أشهر سابقة — الشهر الحالي متروك
     * للمسيّرات التي تولّدها بقية بيانات التجربة.
     */
    private function seedPayrollRuns(): void
    {
        $hr = $this->user(self::PHONE_GM) ?? $this->user(self::PHONE_ADMIN);
        $finance = $this->user(self::PHONE_FINANCE);

        $runs = [
            [3, PayrollRun::STATUS_EXECUTED, 'مسيّر منفذ بالكامل — تم التحويل البنكي.'],
            [2, PayrollRun::STATUS_SUBMITTED, 'مسيّر مرفوع للمالية بانتظار الاعتماد.'],
            [1, PayrollRun::STATUS_DRAFT, 'مسودة مسيّر تحت المراجعة من الموارد البشرية.'],
        ];

        $plan = [
            self::PHONE_GM => [18000, 3000, 0, 0, 0],
            self::PHONE_EXECUTIVE => [18000, 1200, 0, 0, 0],
            self::PHONE_PROJECTS => [12000, 900, 1170, 6, 330],
            self::PHONE_EMPLOYEE => [8000, 600, 0, 4, 160],
        ];

        foreach ($runs as [$monthsAgo, $status, $notes]) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $isExecuted = $status === PayrollRun::STATUS_EXECUTED;
            $isSubmitted = $isExecuted || $status === PayrollRun::STATUS_SUBMITTED;

            $run = PayrollRun::query()->firstOrCreate(
                ['month' => $month->format('Y-m')],
                [
                    'status' => $status,
                    'submitted_by' => $isSubmitted ? $hr?->id : null,
                    'submitted_at' => $isSubmitted ? $month->copy()->endOfMonth() : null,
                    'finance_approved_by' => $isExecuted ? $finance?->id : null,
                    'finance_approved_at' => $isExecuted ? $month->copy()->endOfMonth()->addDay() : null,
                    'notes' => $notes,
                ]
            );

            foreach ($plan as $phone => [$base, $allowances, $deductions, $overtimeHours, $overtimeAmount]) {
                $employee = $this->user($phone);
                if (! $employee) {
                    continue;
                }

                $exists = PayrollRunItem::query()
                    ->where('payroll_run_id', $run->id)
                    ->where('employee_id', $employee->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $item = new PayrollRunItem([
                    'employee_id' => $employee->id,
                    'base' => $base,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'overtime_hours' => $overtimeHours,
                    'overtime_amount' => $overtimeAmount,
                    'variables' => $overtimeHours > 0
                        ? [['label' => 'مكافأة إنجاز', 'reason' => 'إغلاق تقرير الربع في موعده', 'amount' => 500, 'kind' => 'addition']]
                        : [],
                ]);
                $item->payroll_run_id = $run->id;
                $item->recalculate();

                if ($isExecuted) {
                    $item->transfer_reference = 'TRF-'.$month->format('Ym').'-'.$employee->id;
                    $item->transfer_date = $month->copy()->endOfMonth()->addDays(2)->toDateString();
                    $item->proof_file = null;
                    $item->executed_at = $month->copy()->endOfMonth()->addDays(2);
                }

                $item->save();
            }
        }
    }

    /** بنود مسؤوليات — نشطة وموقوفة — تُقاس عليها التقييمات الدورية. */
    private function seedResponsibilities(): void
    {
        $rows = [
            [self::PHONE_EMPLOYEE, 'تنفيذ الأنشطة التوعوية الميدانية حسب الخطة الشهرية', 1, true],
            [self::PHONE_EMPLOYEE, 'إعداد تقرير أسبوعي عن مؤشرات المستفيدين', 2, true],
            [self::PHONE_EMPLOYEE, 'أرشفة نماذج الرضا الورقية', 3, false],
            [self::PHONE_PROJECTS, 'متابعة تنفيذ المشاريع وضمان الالتزام بالجدول الزمني', 1, true],
            [self::PHONE_EXECUTIVE, 'الإشراف على الشراكات ورفع تقارير الأداء لمجلس الإدارة', 1, true],
        ];

        foreach ($rows as [$phone, $body, $order, $isActive]) {
            $employee = $this->user($phone);
            if (! $employee) {
                continue;
            }

            Responsibility::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'order' => $order],
                ['body' => $body, 'is_active' => $isActive]
            );
        }
    }

    /** أربعة تقييمات بفترات مختلفة — منشورة ومسودة — مع درجات على المسؤوليات النشطة. */
    private function seedEvaluations(): void
    {
        $evaluator = $this->user(self::PHONE_GM) ?? $this->user(self::PHONE_ADMIN);

        $rows = [
            [self::PHONE_EMPLOYEE, '2025-Q4', PeriodicEvaluation::STATUS_PUBLISHED, 'أشكر الإدارة على الملاحظات، وسأعمل على تحسين توثيق الأنشطة.'],
            [self::PHONE_EMPLOYEE, '2026-Q1', PeriodicEvaluation::STATUS_PUBLISHED, 'ألتزم برفع التقارير الأسبوعية في موعدها.'],
            [self::PHONE_PROJECTS, '2026-Q2', PeriodicEvaluation::STATUS_DRAFT, null],
            [self::PHONE_EXECUTIVE, '2026-Q3', PeriodicEvaluation::STATUS_DRAFT, null],
        ];

        $notes = [
            5 => 'أداء متميز ومستقر طوال الفترة.',
            4 => 'أداء جيد جدًا مع فرصة بسيطة للتحسين.',
            3 => 'أداء مقبول ويحتاج متابعة أدق للمواعيد.',
        ];

        foreach ($rows as $index => [$phone, $period, $status, $comment]) {
            $employee = $this->user($phone);
            if (! $employee) {
                continue;
            }

            $evaluation = PeriodicEvaluation::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'period' => $period],
                [
                    'evaluator_id' => $evaluator?->id,
                    'status' => $status,
                    'employee_comment' => $comment,
                ]
            );

            $responsibilities = Responsibility::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->orderBy('order')
                ->get(['id']);

            foreach ($responsibilities as $position => $responsibility) {
                $score = [5, 4, 3][($index + $position) % 3];

                EvaluationScore::query()->firstOrCreate(
                    [
                        'periodic_evaluation_id' => $evaluation->id,
                        'responsibility_id' => $responsibility->id,
                    ],
                    ['score' => $score, 'note' => $notes[$score]]
                );
            }
        }
    }

    /** سجلات حضور لآخر خمسة أيام عمل (الأحد–الخميس) لثلاثة موظفين. */
    private function seedAttendance(): void
    {
        $phones = [self::PHONE_EMPLOYEE, self::PHONE_PROJECTS, self::PHONE_EXECUTIVE];
        $types = ['حضور', 'حضور', 'عن بعد', 'تكليف خارجي', 'انقطاع'];

        foreach ($this->lastWorkingDays(5) as $dayIndex => $date) {
            foreach ($phones as $employeeIndex => $phone) {
                $employee = $this->user($phone);
                if (! $employee) {
                    continue;
                }

                $type = $types[($dayIndex + $employeeIndex) % count($types)];
                $absent = $type === 'انقطاع';

                $exists = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', $date->toDateString())
                    ->exists();

                if ($exists) {
                    continue;
                }

                AttendanceRecord::query()->create([
                    'employee_id' => $employee->id,
                    'date' => $date->toDateString(),
                    'check_in_at' => $absent ? null : $date->copy()->setTime(8, 5 + $employeeIndex),
                    'check_out_at' => $absent ? null : $date->copy()->setTime(16, 10 + $employeeIndex),
                    'type' => $type,
                    'declared_by' => $employee->id,
                    'notes' => match ($type) {
                        'عن بعد' => 'عمل عن بعد بموافقة المدير المباشر',
                        'تكليف خارجي' => 'زيارة ميدانية لمقر الشريك',
                        'انقطاع' => 'انقطاع بدون إشعار مسبق',
                        default => null,
                    },
                ]);
            }
        }
    }

    /**
     * إجازات: معتمدة في الشهر الحالي لحساب المدير (تظهر في تقويم المهام لأنه
     * نطاق التقويم يشمل المستخدم ومرؤوسيه)، ومقدمة بانتظار الاعتماد، ومرفوضة،
     * ومعتمدة سابقة. أيام الإجازة السنوية المعتمدة مخصومة مسبقًا من الرصيد.
     */
    private function seedLeaveRequests(): void
    {
        $admin = $this->user(self::PHONE_ADMIN);
        $approver = $this->user(self::PHONE_GM) ?? $admin;

        $monthStart = now()->startOfMonth();

        $rows = [
            [
                'phone' => self::PHONE_ADMIN,
                'type' => LeaveRequest::TYPE_ANNUAL,
                'from' => $monthStart->copy()->addDays(9),
                'to' => $monthStart->copy()->addDays(11),
                'reason' => 'إجازة سنوية قصيرة — مناسبة عائلية',
                'status' => LeaveRequest::STATUS_APPROVED,
                'decided_at' => $monthStart->copy()->addDays(2),
            ],
            [
                'phone' => self::PHONE_EMPLOYEE,
                'type' => LeaveRequest::TYPE_ANNUAL,
                'from' => now()->addDays(7)->startOfDay(),
                'to' => now()->addDays(8)->startOfDay(),
                'reason' => 'ظرف عائلي — بانتظار اعتماد المدير',
                'status' => LeaveRequest::STATUS_SUBMITTED,
                'decided_at' => null,
            ],
            [
                'phone' => self::PHONE_PROJECTS,
                'type' => LeaveRequest::TYPE_EXCEPTIONAL,
                'from' => now()->subDays(20)->startOfDay(),
                'to' => now()->subDays(20)->startOfDay(),
                'reason' => 'طلب إجازة استثنائية تعارض مع تسليم مشروع',
                'status' => LeaveRequest::STATUS_REJECTED,
                'decided_at' => now()->subDays(21),
            ],
            [
                'phone' => self::PHONE_FINANCE,
                'type' => LeaveRequest::TYPE_SICK,
                'from' => now()->subMonth()->startOfMonth()->addDays(14),
                'to' => now()->subMonth()->startOfMonth()->addDays(17),
                'reason' => 'إجازة مرضية بتقرير طبي معتمد',
                'status' => LeaveRequest::STATUS_APPROVED,
                'decided_at' => now()->subMonth()->startOfMonth()->addDays(13),
            ],
        ];

        foreach ($rows as $row) {
            $employee = $this->user($row['phone']);
            if (! $employee) {
                continue;
            }

            $from = $row['from']->copy()->startOfDay();
            $to = $row['to']->copy()->startOfDay();
            $decided = $row['status'] !== LeaveRequest::STATUS_SUBMITTED;

            $exists = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->whereDate('from_date', $from->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            LeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'type' => $row['type'],
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'days_count' => (int) $from->diffInDays($to) + 1,
                'reason' => $row['reason'],
                'status' => $row['status'],
                'approver_id' => $decided ? $approver?->id : null,
                'approved_at' => $decided ? $row['decided_at'] : null,
            ]);
        }
    }

    /**
     * آخر n يوم عمل سعودي (الأحد–الخميس) قبل اليوم، مرتبة تصاعديًا.
     *
     * @return list<Carbon>
     */
    private function lastWorkingDays(int $count): array
    {
        $days = [];
        $cursor = now()->startOfDay()->subDay();

        while (count($days) < $count) {
            if (! in_array($cursor->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $days[] = $cursor->copy();
            }

            $cursor->subDay();
        }

        return array_reverse($days);
    }
}
