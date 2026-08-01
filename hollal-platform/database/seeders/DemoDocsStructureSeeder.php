<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\OrgUnit;
use App\Models\RecurringTaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية لشاشات المستندات والهيكل التنظيمي وأرشيف المحاضر والمهام
 * المتكررة — لتشغيل اختبار القبول (UAT) على شاشات غير فارغة.
 *
 * كل قسم يستخدم firstOrCreate/syncWithoutDetaching فالتشغيل المتكرر لا يكرر
 * الصفوف. مسارات الملفات مؤقتة (demo/…) ولا تُكتب ملفات فعلية على القرص.
 *
 * Time: O(n) إدراجات، n ثابت (~50 صفاً) | Space: O(1) لكل صف.
 */
class DemoDocsStructureSeeder extends Seeder
{
    /** مسار بديل — العمود مطلوب في المخطط ولا يوجد ملف فعلي خلفه. */
    private const PLACEHOLDER_PATH = 'demo/placeholder.pdf';

    public function run(): void
    {
        $admin = User::query()->where('phone', '0500000000')->first();
        $manager = User::query()->where('phone', '0501111111')->first();
        $executive = User::query()->where('phone', '0502222222')->first();

        if (! $admin || ! $manager || ! $executive) {
            $this->command?->warn('DemoDocsStructureSeeder: المستخدمون الأساسيون غير موجودين — نفّذ OnboardingSeeder أولاً.');

            return;
        }

        $projectManager = User::query()->where('phone', '0503333333')->first() ?? $manager;
        $finance = User::query()->where('phone', '0504444444')->first() ?? $manager;
        $employee = User::query()->where('phone', '0505555555')->first() ?? $executive;

        $meetings = $this->seedMeetings($executive, $manager, $projectManager, $employee, $finance);
        $this->seedDocuments($admin, $manager, $executive, $projectManager, $finance, $meetings['board'] ?? null);
        $this->seedPolicies($manager, $executive);
        $this->seedTemplates($admin);
        $committees = $this->seedCommittees($manager, $executive, $projectManager, $finance, $employee);
        $this->linkCommitteeMeeting($meetings['committee'] ?? null, $committees['governance'] ?? null);
        $this->seedOrgTree($manager, $executive, $projectManager, $employee);
        $this->seedRecurringTemplates($manager, $projectManager, $finance, $employee);
        $this->seedAuditLogs($admin, $manager);
    }

    /**
     * ٦ مستندات موزّعة على مستويات السرية الثلاثة + نسخ متعددة لمستندين.
     */
    private function seedDocuments(
        User $admin,
        User $manager,
        User $executive,
        User $projectManager,
        User $finance,
        ?Meeting $boardMeeting,
    ): void {
        $plan = $this->document([
            'title' => 'الخطة التشغيلية للجمعية 2026',
            'category' => 'خطط',
            'confidentiality' => 'team',
            'uploader_id' => $manager->id,
            'path' => self::PLACEHOLDER_PATH,
        ]);

        $this->addVersions($plan, [
            ['version' => 1, 'change_note' => 'الإصدار الأول — مسودة الخطة', 'uploaded_by' => $manager->id],
            ['version' => 2, 'change_note' => 'تعديل مؤشرات الأداء بعد ملاحظات الإدارة التنفيذية', 'uploaded_by' => $executive->id],
            ['version' => 3, 'change_note' => 'اعتماد المجلس مع تحديث الميزانية التقديرية', 'uploaded_by' => $manager->id],
        ]);

        $guide = $this->document([
            'title' => 'دليل إجراءات إدارة المشاريع',
            'category' => 'أدلة',
            'confidentiality' => 'team',
            'uploader_id' => $projectManager->id,
            'path' => self::PLACEHOLDER_PATH,
        ]);

        $this->addVersions($guide, [
            ['version' => 1, 'change_note' => 'الإصدار الأول من الدليل', 'uploaded_by' => $projectManager->id],
            ['version' => 2, 'change_note' => 'إضافة نموذج تقرير الزيارة الميدانية', 'uploaded_by' => $projectManager->id],
        ]);

        $this->document([
            'title' => 'التقرير المالي الربعي — الربع الأول 2026',
            'category' => 'تقارير مالية',
            'confidentiality' => 'department',
            'uploader_id' => $finance->id,
            'path' => self::PLACEHOLDER_PATH,
        ]);

        $this->document([
            'title' => 'محضر الجمعية العمومية العادية 2026',
            'category' => 'محاضر',
            'confidentiality' => 'department',
            'uploader_id' => $manager->id,
            'path' => self::PLACEHOLDER_PATH,
            'is_auto_archived' => true,
            'source_type' => $boardMeeting ? $boardMeeting->getMorphClass() : null,
            'source_id' => $boardMeeting?->id,
        ]);

        $this->document([
            'title' => 'جدول الرواتب المعتمد — يوليو 2026',
            'category' => 'موارد بشرية',
            'confidentiality' => 'managers',
            'uploader_id' => $executive->id,
            'path' => self::PLACEHOLDER_PATH,
        ]);

        $this->document([
            'title' => 'تقييم أداء فريق البرامج — النصف الأول 2026',
            'category' => 'تقييم الأداء',
            'confidentiality' => 'managers',
            'uploader_id' => $admin->id,
            'path' => self::PLACEHOLDER_PATH,
        ]);
    }

    /** ٤ سياسات تظهر في /documents/policies، إحداها مستحقة المراجعة اليوم. */
    private function seedPolicies(User $manager, User $executive): void
    {
        $policies = [
            ['title' => 'سياسة حماية البيانات والخصوصية', 'uploader_id' => $manager->id, 'review_date' => now()->addMonths(6)->toDateString()],
            ['title' => 'سياسة تعارض المصالح', 'uploader_id' => $executive->id, 'review_date' => now()->subDays(10)->toDateString()],
            ['title' => 'سياسة الموارد البشرية واللوائح الداخلية', 'uploader_id' => $manager->id, 'review_date' => now()->addYear()->toDateString()],
            ['title' => 'سياسة المشتريات والعقود', 'uploader_id' => $executive->id, 'review_date' => now()->addMonths(3)->toDateString()],
        ];

        foreach ($policies as $policy) {
            $this->document([
                'title' => $policy['title'],
                'category' => 'سياسة',
                'confidentiality' => 'department',
                'uploader_id' => $policy['uploader_id'],
                'path' => self::PLACEHOLDER_PATH,
                'is_policy' => true,
                'review_date' => $policy['review_date'],
            ]);
        }
    }

    /** مكتبة النماذج الجاهزة (/documents/templates). */
    private function seedTemplates(User $admin): void
    {
        $templates = [
            ['title' => 'نموذج محضر اجتماع', 'category' => 'اجتماعات', 'description' => 'نموذج موحّد لتدوين جدول الأعمال والقرارات والمسؤوليات.'],
            ['title' => 'نموذج طلب صرف مالي', 'category' => 'مالية', 'description' => 'يُرفق مع الفاتورة الضريبية ويُعتمد من الإدارة المالية.'],
            ['title' => 'نموذج تقرير زيارة ميدانية', 'category' => 'مشاريع', 'description' => 'يُعبّأ خلال ٤٨ ساعة من الزيارة ويُرفق بالصور.'],
            ['title' => 'نموذج عقد شراكة مجتمعية', 'category' => 'شراكات', 'description' => 'الصيغة المعتمدة من المستشار القانوني للجمعية.'],
        ];

        foreach ($templates as $template) {
            DocumentTemplate::firstOrCreate(
                ['title' => $template['title']],
                [
                    'category' => $template['category'],
                    'description' => $template['description'],
                    'path' => 'demo/templates/placeholder.docx',
                    'uploaded_by' => $admin->id,
                ]
            );
        }
    }

    /**
     * ٣ لجان مع رئيس وأعضاء — واحدة غير نشطة لاختبار الفلترة.
     *
     * @return array<string, Committee>
     */
    private function seedCommittees(
        User $manager,
        User $executive,
        User $projectManager,
        User $finance,
        User $employee,
    ): array {
        $governance = Committee::firstOrCreate(
            ['name' => 'لجنة الحوكمة والالتزام'],
            [
                'mandate' => 'مراجعة السياسات واللوائح ومتابعة الالتزام بمتطلبات المركز الوطني لتنمية القطاع غير الربحي.',
                'chair_id' => $executive->id,
                'is_active' => true,
            ]
        );
        $governance->members()->syncWithoutDetaching([
            $manager->id => ['role_label' => 'عضو'],
            $projectManager->id => ['role_label' => 'عضو'],
            $finance->id => ['role_label' => 'مقرر اللجنة'],
        ]);

        $procurement = Committee::firstOrCreate(
            ['name' => 'لجنة المشتريات'],
            [
                'mandate' => 'دراسة عروض الموردين واعتماد أوامر الشراء التي تتجاوز خمسة آلاف ريال.',
                'chair_id' => $manager->id,
                'is_active' => true,
            ]
        );
        $procurement->members()->syncWithoutDetaching([
            $finance->id => ['role_label' => 'عضو'],
            $employee->id => ['role_label' => 'أمين اللجنة'],
        ]);

        $risk = Committee::firstOrCreate(
            ['name' => 'لجنة إدارة المخاطر'],
            [
                'mandate' => 'حصر مخاطر التشغيل والتمويل ورفع خطة المعالجة للمجلس (موقوفة مؤقتاً).',
                'chair_id' => $manager->id,
                'is_active' => false,
            ]
        );
        $risk->members()->syncWithoutDetaching([
            $executive->id => ['role_label' => 'عضو'],
        ]);

        return ['governance' => $governance, 'procurement' => $procurement, 'risk' => $risk];
    }

    /** ربط اجتماع اللجنة بلجنة الحوكمة (عمود meetings.committee_id). */
    private function linkCommitteeMeeting(?Meeting $meeting, ?Committee $committee): void
    {
        if (! $meeting || ! $committee || $meeting->committee_id === $committee->id) {
            return;
        }

        $meeting->forceFill(['committee_id' => $committee->id])->save();
    }

    /** شجرة: إدارة ← وحدة ← وظيفة، مع مسؤول لكل عقدة. */
    private function seedOrgTree(User $manager, User $executive, User $projectManager, User $employee): void
    {
        $executiveDept = Department::query()->where('name', 'الإدارة التنفيذية')->first();
        $projectsDept = Department::query()->where('name', 'إدارة المشاريع')->first();

        $executiveAdmin = $this->orgUnit('الإدارة التنفيذية', OrgUnit::LEVEL_ADMINISTRATION, null, [
            'department_id' => $executiveDept?->id,
            'manager_id' => $executive->id,
            'position' => 0,
        ]);

        $planningUnit = $this->orgUnit('وحدة التخطيط والمتابعة', OrgUnit::LEVEL_UNIT, $executiveAdmin, [
            'department_id' => $executiveDept?->id,
            'manager_id' => $manager->id,
            'position' => 0,
        ]);

        $this->orgUnit('أخصائي تخطيط ومتابعة', OrgUnit::LEVEL_JOB, $planningUnit, [
            'department_id' => $executiveDept?->id,
            'manager_id' => $manager->id,
            'position' => 0,
            'job_purpose' => 'إعداد الخطة التشغيلية ومتابعة مؤشرات الأداء ربع السنوية ورفع تقارير الإنجاز.',
            'job_responsibilities' => [
                'بناء الخطة التشغيلية السنوية بالتنسيق مع الإدارات',
                'متابعة مؤشرات الأداء وتحديث لوحة المتابعة شهرياً',
                'إعداد تقرير الإنجاز الربعي لمجلس الإدارة',
            ],
            'job_requirements' => [
                'بكالوريوس إدارة أعمال أو ما يعادله',
                'خبرة سنتين في التخطيط والمتابعة بالقطاع غير الربحي',
                'إتقان جداول البيانات وأدوات التحليل',
            ],
        ]);

        $governanceUnit = $this->orgUnit('وحدة الحوكمة والالتزام', OrgUnit::LEVEL_UNIT, $executiveAdmin, [
            'department_id' => $executiveDept?->id,
            'manager_id' => $executive->id,
            'position' => 1,
        ]);

        $this->orgUnit('مسؤول الحوكمة والالتزام', OrgUnit::LEVEL_JOB, $governanceUnit, [
            'department_id' => $executiveDept?->id,
            'manager_id' => $executive->id,
            'position' => 0,
            'job_purpose' => 'ضمان التزام الجمعية باللوائح المنظمة ومراجعة السياسات في مواعيد مراجعتها.',
            'job_responsibilities' => [
                'مراجعة السياسات الداخلية سنوياً',
                'متابعة تنفيذ قرارات لجنة الحوكمة',
                'إعداد تقرير الالتزام للجهة المشرفة',
            ],
            'job_requirements' => [
                'معرفة بأنظمة القطاع غير الربحي',
                'مهارات صياغة السياسات والتقارير',
            ],
        ]);

        $projectsAdmin = $this->orgUnit('إدارة المشاريع والبرامج', OrgUnit::LEVEL_ADMINISTRATION, null, [
            'department_id' => $projectsDept?->id,
            'manager_id' => $projectManager->id,
            'position' => 1,
        ]);

        $executionUnit = $this->orgUnit('وحدة تنفيذ المشاريع', OrgUnit::LEVEL_UNIT, $projectsAdmin, [
            'department_id' => $projectsDept?->id,
            'manager_id' => $projectManager->id,
            'position' => 0,
        ]);

        $this->orgUnit('منسق مشاريع', OrgUnit::LEVEL_JOB, $executionUnit, [
            'department_id' => $projectsDept?->id,
            'manager_id' => $projectManager->id,
            'position' => 0,
            'job_purpose' => 'تنسيق تنفيذ المبادرات الميدانية وضبط الجدول الزمني والمستفيدين.',
            'job_responsibilities' => [
                'إعداد خطة التنفيذ الأسبوعية للمبادرات',
                'حصر المستفيدين وتوثيق الاستحقاق',
                'رفع تقرير الزيارة الميدانية بعد كل زيارة',
            ],
            'job_requirements' => [
                'خبرة سنة في تنسيق المشاريع المجتمعية',
                'القدرة على العمل الميداني والتنقل',
            ],
        ]);

        $this->orgUnit('أخصائي متابعة المستفيدين', OrgUnit::LEVEL_JOB, $executionUnit, [
            'department_id' => $projectsDept?->id,
            'manager_id' => $employee->id,
            'position' => 1,
            'job_purpose' => 'التحقق من بيانات المستفيدين وقياس أثر الخدمات المقدمة لهم.',
            'job_responsibilities' => [
                'تدقيق طلبات المستفيدين قبل الاعتماد',
                'إجراء مسح رضا المستفيدين نصف السنوي',
            ],
            'job_requirements' => [
                'مهارات تواصل عالية',
                'إلمام بأنظمة حماية بيانات المستفيدين',
            ],
        ]);
    }

    /** ٣ قوالب مهام متكررة بترددات مختلفة (أسبوعي/شهري) مع قالب موقوف. */
    private function seedRecurringTemplates(User $manager, User $projectManager, User $finance, User $employee): void
    {
        RecurringTaskTemplate::firstOrCreate(
            ['title' => 'تقرير المتابعة الأسبوعي للمشاريع'],
            [
                'description' => 'تجميع مستجدات المبادرات الجارية ورفعها للإدارة التنفيذية صباح كل أحد.',
                'required_evidence' => 'ملف التقرير الأسبوعي بصيغة PDF',
                'assigned_to_id' => $projectManager->id,
                'created_by' => $manager->id,
                'pattern' => RecurringTaskTemplate::PATTERN_WEEKLY,
                'day_of_week' => 0,
                'is_active' => true,
            ]
        );

        RecurringTaskTemplate::firstOrCreate(
            ['title' => 'إقفال الحسابات الشهري'],
            [
                'description' => 'مطابقة الحسابات البنكية وإصدار ميزان المراجعة قبل نهاية الشهر.',
                'required_evidence' => 'ميزان المراجعة وكشف الحساب البنكي',
                'assigned_to_id' => $finance->id,
                'created_by' => $manager->id,
                'pattern' => RecurringTaskTemplate::PATTERN_MONTHLY,
                'day_of_month' => 28,
                'is_active' => true,
            ]
        );

        RecurringTaskTemplate::firstOrCreate(
            ['title' => 'جرد مستودع السلال الغذائية'],
            [
                'description' => 'جرد الكميات المتبقية ومطابقتها مع سجل الصرف (موقوف حتى استئناف البرنامج).',
                'required_evidence' => 'محضر الجرد موقّعاً من أمين المستودع',
                'assigned_to_id' => $employee->id,
                'created_by' => $manager->id,
                'pattern' => RecurringTaskTemplate::PATTERN_MONTHLY,
                'day_of_month' => 1,
                'is_active' => false,
            ]
        );
    }

    /**
     * ٣ اجتماعات معتمدة (أرشيف المحاضر) مع بنود قرارات — أحدها قرار متأخر مفتوح.
     *
     * @return array<string, Meeting>
     */
    private function seedMeetings(
        User $executive,
        User $manager,
        User $projectManager,
        User $employee,
        User $finance,
    ): array {
        $board = $this->meeting([
            'title' => 'اجتماع مجلس الإدارة الدوري — الربع الثاني 2026',
            'type' => 'دوري',
            'scheduled_at' => now()->subDays(52)->setTime(10, 0),
            'agenda' => 'اعتماد الخطة التشغيلية، مراجعة المركز المالي، مستجدات الشراكات.',
            'location' => 'مقر الجمعية — قاعة الاجتماعات الرئيسية',
            'chair_id' => $manager->id,
            'secretary_id' => $executive->id,
            'approved_by' => $manager->id,
        ]);

        $this->meetingItem($board, [
            'topic' => 'اعتماد الخطة التشغيلية 2026',
            'item_kind' => 'قرار',
            'proposed_by' => $executive->id,
            'discussion_summary' => 'استعرض المجلس مسودة الخطة ومؤشرات الأداء المقترحة والميزانية التقديرية.',
            'decision' => 'اعتماد الخطة التشغيلية مع رفع مؤشر عدد المستفيدين إلى ١٢٠٠ مستفيد.',
            'responsible_id' => $executive->id,
            'due_date' => now()->subDays(20)->toDateString(),
            'status' => 'done',
        ]);

        $this->meetingItem($board, [
            'topic' => 'مراجعة المركز المالي للربع الأول',
            'item_kind' => 'قرار',
            'proposed_by' => $finance->id,
            'discussion_summary' => 'نسبة الصرف التشغيلي بلغت ٤٢٪ من الميزانية المعتمدة.',
            'decision' => 'إعداد تقرير تفصيلي بمصروفات التشغيل ورفعه في الاجتماع القادم.',
            'responsible_id' => $finance->id,
            'due_date' => now()->addDays(12)->toDateString(),
            'status' => 'in_progress',
        ]);

        $committeeMeeting = $this->meeting([
            'title' => 'اجتماع لجنة الحوكمة والالتزام — مراجعة السياسات',
            'type' => 'لجنة',
            'scheduled_at' => now()->subDays(30)->setTime(11, 30),
            'agenda' => 'مراجعة سياسة تعارض المصالح وسياسة المشتريات وتحديث تواريخ المراجعة.',
            'location' => 'مقر الجمعية — قاعة اللجان',
            'chair_id' => $executive->id,
            'secretary_id' => $manager->id,
            'approved_by' => $executive->id,
        ]);

        $this->meetingItem($committeeMeeting, [
            'topic' => 'تحديث سياسة تعارض المصالح',
            'item_kind' => 'قرار',
            'proposed_by' => $executive->id,
            'discussion_summary' => 'لوحظ تجاوز تاريخ المراجعة المحدد للسياسة الحالية.',
            'decision' => 'تحديث السياسة وإعادة اعتمادها من المجلس خلال شهر.',
            'responsible_id' => $manager->id,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'open',
        ]);

        $this->meetingItem($committeeMeeting, [
            'topic' => 'مستوى الالتزام بمتطلبات الجهة المشرفة',
            'item_kind' => 'نقاشي',
            'proposed_by' => $manager->id,
            'discussion_summary' => 'استعراض متطلبات الإفصاح السنوي والمستندات الناقصة.',
            'status' => 'open',
        ]);

        $urgent = $this->meeting([
            'title' => 'اجتماع طارئ — تأخر توريد السلال الغذائية',
            'type' => 'طارئ',
            'scheduled_at' => now()->subDays(47)->setTime(9, 0),
            'agenda' => 'أسباب تأخر المورد وبدائل التوريد وأثرها على موعد التوزيع.',
            'location' => 'اجتماع عن بُعد',
            'link' => 'https://meet.example.sa/hollal-demo',
            'chair_id' => $manager->id,
            'secretary_id' => $projectManager->id,
            'approved_by' => $manager->id,
        ]);

        $overdue = $this->meetingItem($urgent, [
            'topic' => 'التعاقد مع مورد بديل للسلال الغذائية',
            'item_kind' => 'قرار',
            'proposed_by' => $projectManager->id,
            'discussion_summary' => 'المورد الحالي تأخر ثلاثة أسابيع عن الموعد التعاقدي دون مبرر.',
            'decision' => 'استدراج ثلاثة عروض من موردين بدلاء والتعاقد مع الأنسب.',
            'responsible_id' => $projectManager->id,
            'due_date' => now()->subDays(18)->toDateString(),
            'status' => 'open',
        ]);

        // قرار متأخر: عمره يتجاوز ٣٠ يوماً ليظهر ضمن MeetingItem::stale().
        if ($overdue->wasRecentlyCreated) {
            $overdue->forceFill([
                'created_at' => now()->subDays(47),
                'updated_at' => now()->subDays(47),
            ])->save();
        }

        $this->meetingItem($urgent, [
            'topic' => 'إشعار المستفيدين بموعد التوزيع الجديد',
            'item_kind' => 'قرار',
            'proposed_by' => $manager->id,
            'discussion_summary' => 'اتُفق على إرسال رسائل نصية للمستفيدين المسجلين.',
            'decision' => 'إرسال إشعار للمستفيدين بالموعد الجديد خلال ثلاثة أيام.',
            'responsible_id' => $employee->id,
            'due_date' => now()->subDays(40)->toDateString(),
            'status' => 'done',
        ]);

        return ['board' => $board, 'committee' => $committeeMeeting, 'urgent' => $urgent];
    }

    /** سجل نشاط تجريبي — يُكتب عبر النموذج المخصص للسجل الملحق فقط. */
    private function seedAuditLogs(User $admin, User $manager): void
    {
        $entries = [
            ['action' => 'settings.updated', 'actor_id' => $admin->id, 'metadata' => ['key' => 'platform.name'], 'created_at' => now()->subDays(9)],
            ['action' => 'permissions.role_synced', 'actor_id' => $admin->id, 'metadata' => ['role' => 'Project Manager'], 'created_at' => now()->subDays(6)],
            ['action' => 'backup.created', 'actor_id' => $admin->id, 'metadata' => ['size_mb' => 14], 'created_at' => now()->subDays(3)],
            ['action' => 'report.exported', 'actor_id' => $manager->id, 'metadata' => ['report' => 'التقرير المالي الربعي'], 'created_at' => now()->subDay()],
        ];

        foreach ($entries as $entry) {
            AuditLog::firstOrCreate(
                ['action' => $entry['action'], 'actor_id' => $entry['actor_id']],
                [
                    'metadata' => $entry['metadata'],
                    'ip_address' => '127.0.0.1',
                    'created_at' => $entry['created_at'],
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function document(array $attributes): Document
    {
        $title = $attributes['title'];
        unset($attributes['title']);

        return Document::firstOrCreate(['title' => $title], $attributes);
    }

    /**
     * نسخ تراكمية: لا تُستبدل النسخة السابقة، ويشير المستند إلى الأحدث.
     *
     * @param  list<array<string, mixed>>  $versions
     */
    private function addVersions(Document $document, array $versions): void
    {
        foreach ($versions as $version) {
            DocumentVersion::firstOrCreate(
                ['document_id' => $document->id, 'version' => $version['version']],
                [
                    'path' => 'demo/documents/'.$document->id.'/v'.$version['version'].'-placeholder.pdf',
                    'change_note' => $version['change_note'],
                    'uploaded_by' => $version['uploaded_by'],
                ]
            );
        }

        $latest = (int) $document->versions()->max('version');

        if ($latest > 0 && $document->current_version !== $latest) {
            $document->forceFill([
                'current_version' => $latest,
                'path' => 'demo/documents/'.$document->id.'/v'.$latest.'-placeholder.pdf',
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function orgUnit(string $name, string $level, ?OrgUnit $parent, array $attributes): OrgUnit
    {
        return OrgUnit::firstOrCreate(
            ['name' => $name, 'level' => $level],
            array_merge($attributes, ['parent_id' => $parent?->id])
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function meeting(array $attributes): Meeting
    {
        $title = $attributes['title'];
        unset($attributes['title']);

        return Meeting::firstOrCreate(['title' => $title], array_merge($attributes, [
            'status' => 'completed',
            'approval_status' => Meeting::APPROVAL_APPROVED,
            'approved_at' => $attributes['scheduled_at']->copy()->addDays(2),
            'version' => 1,
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function meetingItem(Meeting $meeting, array $attributes): MeetingItem
    {
        $topic = $attributes['topic'];
        unset($attributes['topic']);

        return MeetingItem::firstOrCreate(
            ['meeting_id' => $meeting->id, 'topic' => $topic],
            $attributes
        );
    }
}
