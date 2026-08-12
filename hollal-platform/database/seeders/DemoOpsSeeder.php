<?php

namespace Database\Seeders;

use App\Models\ExceptionalGrant;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\TaskStatusLog;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskDueSoon;
use App\Notifications\TaskOverdue;
use App\Notifications\WeeklyReportGenerated;
use App\Services\ReportCenterService;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification as NotificationContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * بيانات تشغيلية تجريبية (UAT) للشاشات التي تبقى فارغة بعد بقية ملفات العرض:
 * إسناد المهام وتقويمها ولوحة العبء، حضور الاجتماعات، الاستثناءات، تحديثات
 * المشاريع، التقارير الأسبوعية ولقطاتها، وجرس الإشعارات.
 *
 * كل قسم محمي بمفتاح طبيعي (firstOrCreate أو فحص وجود)، فتشغيله مرتين لا يكرر
 * صفاً واحداً. لا تُكتب أي ملفات على القرص وتُترك أعمدة المسارات فارغة.
 *
 * Time: O(n) إدراجات على مجموعة ثابتة (~60 صفاً) | Space: O(n) للصفوف المنشأة.
 */
class DemoOpsSeeder extends Seeder
{
    private const PHONE_ADMIN = '0500000000';

    private const PHONE_GM = '0501111111';

    private const PHONE_EXECUTIVE = '0502222222';

    private const PHONE_PROJECTS = '0503333333';

    private const PHONE_FINANCE = '0504444444';

    private const PHONE_EMPLOYEE = '0505555555';

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Project> */
    private array $projects = [];

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

        // بدون حساب المدير وموظف واحد على الأقل لا معنى للبيانات — نخرج بدل الانهيار.
        if (! $this->user(self::PHONE_ADMIN) || ! $this->user(self::PHONE_EMPLOYEE)) {
            $this->command?->warn('DemoOpsSeeder: مستخدمو العرض غير موجودين — نفّذ OnboardingSeeder أولاً.');

            return;
        }

        $this->projects = Project::query()
            ->orderBy('id')
            ->get()
            ->keyBy(fn (Project $project) => $project->name)
            ->all();

        $this->seedReportingLines();
        $this->seedTasks();
        $this->seedMeetingAttendees();
        $this->seedExceptionalGrants();
        $this->seedProjectUpdates();
        $this->seedWeeklyReports();
        $this->seedReportSnapshots();
        $this->seedNotifications();
    }

    private function user(string $phone): ?User
    {
        return $this->users[$phone] ?? null;
    }

    /** المشروع بالاسم الكامل، وإلا أول مشروع متاح، وإلا null. */
    private function project(string $name): ?Project
    {
        return $this->projects[$name] ?? null;
    }

    /**
     * لوحة عبء الفريق و«مهام الفريق» تعتمدان على users.manager_id، ولا يزرعه أي
     * ملف عرض آخر — نربط ثلاثة موظفين بحساب المدير فقط إن كان الحقل فارغاً.
     */
    private function seedReportingLines(): void
    {
        $admin = $this->user(self::PHONE_ADMIN);

        foreach ([self::PHONE_PROJECTS, self::PHONE_FINANCE, self::PHONE_EMPLOYEE] as $phone) {
            $member = $this->user($phone);

            if (! $member || $member->manager_id !== null || $member->id === $admin->id) {
                continue;
            }

            $member->forceFill(['manager_id' => $admin->id])->save();
        }
    }

    /**
     * ١٢ مهمة تغطي الحالات الخمس (new/in_progress/pending_review/completed/overdue)
     * وخمسة مُسنَد إليهم، منها متأخرة، ومستحقة داخل الشهر الجاري (لتظهر في
     * التقويم)، ومرتبطة بالمشاريع القائمة، مع ملاحظات وسجل تغيّر حالة.
     */
    private function seedTasks(): void
    {
        $admin = $this->user(self::PHONE_ADMIN);
        $gm = $this->user(self::PHONE_GM) ?? $admin;
        $executive = $this->user(self::PHONE_EXECUTIVE) ?? $admin;
        $projects = $this->user(self::PHONE_PROJECTS) ?? $executive;
        $finance = $this->user(self::PHONE_FINANCE) ?? $executive;
        $employee = $this->user(self::PHONE_EMPLOYEE);

        $itqan = $this->project('مشروع إتقان — حلقات جمعية الخرج');
        $leaders = $this->project('مشروع القادة الصغار — مدارس الرياض');
        $teacher = $this->project('مشروع المعلم الملهم — النسخة الداخلية');

        $monthStart = now()->startOfMonth();

        $definitions = [
            [
                'title' => 'إعداد تقرير الزيارة الميدانية لحلقة الخرج الثانية',
                'description' => 'توثيق ملاحظات الزيارة الأخيرة وإرفاق كشف الحضور وصور القاعة.',
                'required_evidence' => 'تقرير الزيارة بصيغة PDF + كشف الحضور',
                'assigned_to' => $employee,
                'assigned_by' => $admin,
                'project' => $itqan,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => $monthStart->copy()->addDays(11)->setTime(14, 0),
                'logs' => [['new', 'in_progress', 'بدأ العمل على التقرير', 2]],
                'notes' => [
                    [$employee, 'تم تجميع كشف الحضور من مشرف الحلقة، وبقي إرفاق الصور.'],
                    [$admin, 'يرجى إبراز أثر السماعات الجديدة على مستوى التفاعل.'],
                ],
            ],
            [
                'title' => 'تحديث كشف حضور طلاب حلقات إتقان',
                'description' => 'إدخال حضور الأسبوع الحالي في سجل المستفيدين.',
                'required_evidence' => null,
                'assigned_to' => $employee,
                'assigned_by' => $projects,
                'project' => $itqan,
                'priority' => 'medium',
                'status' => 'new',
                'due_date' => $monthStart->copy()->addDays(1)->setTime(12, 0),
                'logs' => [],
                'notes' => [],
            ],
            [
                'title' => 'رفع محضر ورشة المعلم الملهم إلى الأرشيف',
                'description' => 'أرشفة المحضر الموقّع ضمن مستندات المشروع بعد اعتماده.',
                'required_evidence' => 'المحضر موقّعاً من رئيس الجلسة',
                'assigned_to' => $projects,
                'assigned_by' => $admin,
                'project' => $teacher,
                'priority' => 'medium',
                'status' => 'completed',
                'due_date' => now()->subDays(9)->setTime(15, 0),
                'completed_at' => now()->subDays(6),
                'self_rating' => 'متميز',
                'pm_rating' => 'متميز',
                'final_rating' => 'متميز',
                'final_notes' => 'أُنجزت قبل الموعد وبتوثيق كامل.',
                'submission_note' => 'رُفع المحضر بعد مطابقته مع تسجيل الجلسة.',
                'logs' => [
                    ['new', 'in_progress', 'استلام المحضر من المقرر', 12],
                    ['in_progress', 'pending_review', 'رفع المحضر للاعتماد', 7],
                    ['pending_review', 'completed', 'اعتماد المهمة بتقييم متميز', 6],
                ],
                'notes' => [
                    [$projects, 'المحضر مطابق للتسجيل ولا توجد ملاحظات على القرارات.'],
                ],
            ],
            [
                'title' => 'تجهيز الحقائب التدريبية لمدارس القادة الصغار',
                'description' => 'طباعة وتغليف ٤٠٠ حقيبة وتوزيعها على المدارس الأربع.',
                'required_evidence' => 'إشعار استلام من كل مدرسة',
                'assigned_to' => $employee,
                'assigned_by' => $projects,
                'project' => $leaders,
                'priority' => 'high',
                'status' => 'overdue',
                'due_date' => now()->subDays(6)->setTime(16, 0),
                'logs' => [
                    ['new', 'in_progress', 'بدء التنسيق مع المطبعة', 15],
                    ['in_progress', 'overdue', 'تأخر تسليم المطبعة عن الموعد', 6],
                ],
                'notes' => [
                    [$employee, 'المطبعة اعتذرت عن الموعد الأول ووعدت بالتسليم خلال أسبوع.'],
                ],
            ],
            [
                'title' => 'متابعة خطاب تسهيل المهمة من إدارة التعليم',
                'description' => 'مخاطبة إدارة النشاط الطلابي لاستلام الخطاب قبل انطلاق المشروع.',
                'required_evidence' => null,
                'assigned_to' => $executive,
                'assigned_by' => $admin,
                'project' => $leaders,
                'priority' => 'urgent',
                'status' => 'in_progress',
                'due_date' => $monthStart->copy()->addDays(17)->setTime(11, 0),
                'logs' => [['new', 'in_progress', 'إرسال الخطاب الرسمي', 3]],
                'notes' => [
                    [$executive, 'وُعدنا بالرد خلال ثلاثة أيام عمل من إدارة النشاط الطلابي.'],
                ],
            ],
            [
                'title' => 'مطابقة مصروفات الربع الثاني مع كشف الحساب البنكي',
                'description' => 'مطابقة الصرف التشغيلي مع الكشف البنكي وبيان الفروقات.',
                'required_evidence' => 'ميزان المراجعة وكشف الحساب',
                'assigned_to' => $finance,
                'assigned_by' => $admin,
                'project' => null,
                'priority' => 'high',
                'status' => 'pending_review',
                'due_date' => $monthStart->copy()->addDays(19)->setTime(13, 0),
                'self_rating' => 'متوسط',
                'submission_note' => 'الفروقات محصورة في ثلاث عمليات بانتظار إشعار البنك.',
                'logs' => [
                    ['new', 'in_progress', 'بدء المطابقة', 8],
                    ['in_progress', 'pending_review', 'رفع المطابقة لاعتماد المدير', 1],
                ],
                'notes' => [
                    [$finance, 'العمليات الثلاث المعلّقة تخص تحويلات نهاية الشهر.'],
                ],
            ],
            [
                'title' => 'إعداد مسودة الميزانية التشغيلية للربع الثالث',
                'description' => 'بناء المسودة على أساس صرف الربعين السابقين وخطة البرامج.',
                'required_evidence' => 'ملف الميزانية التقديرية',
                'assigned_to' => $finance,
                'assigned_by' => $admin,
                'project' => null,
                'priority' => 'medium',
                'status' => 'pending_review',
                'due_date' => $monthStart->copy()->addDays(21)->setTime(10, 30),
                'self_rating' => 'مقبول',
                'submission_note' => 'المسودة جاهزة وتحتاج اعتماد بند التدريب.',
                'logs' => [
                    ['new', 'in_progress', 'تجميع أرقام الربعين السابقين', 10],
                    ['in_progress', 'pending_review', 'رفع المسودة للاعتماد', 2],
                ],
                'notes' => [],
            ],
            [
                'title' => 'توثيق الدروس المستفادة من مشروع المعلم الملهم',
                'description' => 'صياغة الدروس المستفادة وإدراجها في ملف إغلاق المشروع.',
                'required_evidence' => null,
                'assigned_to' => $projects,
                'assigned_by' => $executive,
                'project' => $teacher,
                'priority' => 'low',
                'status' => 'completed',
                'due_date' => now()->subDays(16)->setTime(12, 0),
                'completed_at' => now()->subDays(13),
                'self_rating' => 'متوسط',
                'pm_rating' => 'متوسط',
                'final_rating' => 'متوسط',
                'final_notes' => 'التوثيق جيد لكنه تأخر يومين عن الموعد.',
                'logs' => [
                    ['new', 'in_progress', 'مراجعة تقارير الورش', 20],
                    ['in_progress', 'pending_review', 'رفع الصياغة النهائية', 14],
                    ['pending_review', 'completed', 'اعتماد المهمة', 13],
                ],
                'notes' => [],
            ],
            [
                'title' => 'تحديث بيانات المستفيدين في سجل الجمعية',
                'description' => 'تدقيق أرقام التواصل وحالة الاستحقاق لجميع المستفيدين المسجلين.',
                'required_evidence' => 'كشف المستفيدين بعد التدقيق',
                'assigned_to' => $employee,
                'assigned_by' => $admin,
                'project' => null,
                'priority' => 'urgent',
                'status' => 'overdue',
                'due_date' => now()->subDays(20)->setTime(16, 0),
                'logs' => [
                    ['new', 'in_progress', 'بدء التدقيق على الدفعة الأولى', 26],
                    ['in_progress', 'overdue', 'تجاوز الموعد بانتظار بيانات الجهة', 20],
                ],
                'notes' => [
                    [$employee, 'ينقصنا تحديث ١٤٠ رقم تواصل، وطلبناها من منسق الجهة.'],
                    [$admin, 'التأخير مؤثر — يرجى رفع خطة إنهاء خلال أسبوع.'],
                ],
            ],
            [
                'title' => 'مراجعة عقد شراكة منارات التعليم قبل التوقيع',
                'description' => 'مراجعة بنود الالتزامات وجدول الدفعات قبل عرضه على المستشار.',
                'required_evidence' => null,
                'assigned_to' => $admin,
                'assigned_by' => $gm,
                'project' => null,
                'priority' => 'high',
                'status' => 'new',
                'due_date' => $monthStart->copy()->addDays(8)->setTime(9, 30),
                'logs' => [],
                'notes' => [],
            ],
            [
                'title' => 'إعداد عرض مجلس الإدارة عن مؤشرات الربع',
                'description' => 'تجميع مؤشرات الأداء والإنفاق وإعداد عرض من عشر شرائح.',
                'required_evidence' => 'ملف العرض النهائي',
                'assigned_to' => $admin,
                'assigned_by' => $executive,
                'project' => null,
                'priority' => 'medium',
                'status' => 'in_progress',
                'due_date' => $monthStart->copy()->addDays(13)->setTime(15, 0),
                'logs' => [['new', 'in_progress', 'تجميع المؤشرات من الإدارات', 4]],
                'notes' => [
                    [$admin, 'بانتظار أرقام الإدارة المالية لإكمال شريحة الإنفاق.'],
                ],
            ],
            [
                'title' => 'أرشفة الفواتير الضريبية لشهر يوليو',
                'description' => 'رفع الفواتير الضريبية الصادرة والواردة إلى الأرشيف المالي.',
                'required_evidence' => null,
                'assigned_to' => $projects,
                'assigned_by' => $admin,
                'project' => null,
                'priority' => 'low',
                'status' => 'new',
                'due_date' => $monthStart->copy()->addDays(24)->setTime(14, 0),
                'logs' => [],
                'notes' => [],
            ],
        ];

        foreach ($definitions as $definition) {
            $assignee = $definition['assigned_to'];
            $assigner = $definition['assigned_by'];

            if (! $assignee || ! $assigner) {
                continue;
            }

            $task = Task::firstOrCreate(
                ['title' => $definition['title']],
                [
                    'description' => $definition['description'],
                    'required_evidence' => $definition['required_evidence'],
                    'type' => 'single',
                    'assigned_to' => $assignee->id,
                    'assigned_by' => $assigner->id,
                    'project_id' => $definition['project']?->id,
                    'priority' => $definition['priority'],
                    'status' => $definition['status'],
                    'due_date' => $definition['due_date'],
                    'submission_note' => $definition['submission_note'] ?? null,
                    'self_rating' => $definition['self_rating'] ?? null,
                    'pm_rating' => $definition['pm_rating'] ?? null,
                    'final_rating' => $definition['final_rating'] ?? null,
                    'final_notes' => $definition['final_notes'] ?? null,
                    'completed_at' => $definition['completed_at'] ?? null,
                ],
            );

            foreach ($definition['logs'] as [$from, $to, $note, $daysAgo]) {
                TaskStatusLog::firstOrCreate(
                    ['task_id' => $task->id, 'to_status' => $to],
                    [
                        'from_status' => $from,
                        'changed_by' => $assigner->id,
                        'note' => $note,
                        'created_at' => now()->subDays($daysAgo),
                    ],
                );
            }

            foreach ($definition['notes'] as [$author, $body]) {
                if (! $author) {
                    continue;
                }

                TaskNote::firstOrCreate(
                    ['task_id' => $task->id, 'body' => $body],
                    ['author_id' => $author->id],
                );
            }
        }
    }

    /**
     * حضور الاجتماعات القائمة — الجدول meeting_user يحمل المفتاحين والطوابع
     * الزمنية فقط، والفهرس الفريد يمنع التكرار.
     */
    private function seedMeetingAttendees(): void
    {
        $pool = collect([
            self::PHONE_ADMIN, self::PHONE_GM, self::PHONE_EXECUTIVE,
            self::PHONE_PROJECTS, self::PHONE_FINANCE, self::PHONE_EMPLOYEE,
        ])
            ->map(fn (string $phone) => $this->user($phone))
            ->filter()
            ->values();

        if ($pool->isEmpty()) {
            return;
        }

        Meeting::query()->orderBy('id')->get()->each(function (Meeting $meeting, int $index) use ($pool) {
            $rotating = $pool
                ->concat($pool)
                ->slice($index % $pool->count(), 4)
                ->pluck('id');

            $attendees = collect([$meeting->chair_id, $meeting->secretary_id])
                ->filter()
                ->merge($rotating)
                ->unique()
                ->values()
                ->all();

            if ($attendees === []) {
                return;
            }

            $meeting->attendees()->syncWithoutDetaching($attendees);
        });
    }

    /** ثلاثة استثناءات: سارٍ بمدة، سارٍ بلا انتهاء، ومنتهٍ. */
    private function seedExceptionalGrants(): void
    {
        $admin = $this->user(self::PHONE_ADMIN);

        $rows = [
            [
                'phone' => self::PHONE_EXECUTIVE,
                'permission' => 'finance.expenses.approve',
                'reason' => 'تفويض مؤقت باعتماد طلبات الصرف أثناء إجازة المدير المالي',
                'granted_on' => now()->subDays(20),
                'expires_on' => now()->addDays(40),
            ],
            [
                'phone' => self::PHONE_FINANCE,
                'permission' => 'projects.view',
                'reason' => 'اطلاع دائم على بيانات المشاريع لمطابقة الصرف بالميزانيات المعتمدة',
                'granted_on' => now()->subDays(10),
                'expires_on' => null,
            ],
            [
                'phone' => self::PHONE_PROJECTS,
                'permission' => 'reports.export',
                'reason' => 'تصدير تقارير الأثر للجهة المانحة خلال فترة التقرير الربعي',
                'granted_on' => now()->subDays(75),
                'expires_on' => now()->subDays(15),
            ],
        ];

        foreach ($rows as $row) {
            $user = $this->user($row['phone']);

            if (! $user) {
                continue;
            }

            ExceptionalGrant::firstOrCreate(
                ['user_id' => $user->id, 'permission' => $row['permission']],
                [
                    'reason' => $row['reason'],
                    'granted_on' => $row['granted_on']->toDateString(),
                    'expires_on' => $row['expires_on']?->toDateString(),
                    'granted_by' => $admin?->id,
                    'revoked_at' => null,
                ],
            );
        }
    }

    /** تحديثات أسبوعية تراكمية لكل مشروع قائم — تظهر في تبويب «التحديثات». */
    private function seedProjectUpdates(): void
    {
        $executive = $this->user(self::PHONE_EXECUTIVE) ?? $this->user(self::PHONE_ADMIN);
        $projectManager = $this->user(self::PHONE_PROJECTS) ?? $executive;

        $rows = [
            [
                'project' => 'مشروع إتقان — حلقات جمعية الخرج',
                'author' => $projectManager,
                'done' => 'تنفيذ الجلسات من الأولى إلى السادسة في الحلقات الثلاث بحضور ٦٨ طالباً.',
                'next' => 'البدء بالجلسة السابعة وتوزيع اختبار منتصف البرنامج.',
                'blockers' => 'ضعف الصوتيات في القاعة الثانية أثّر على جودة الجلسة الرابعة.',
                'decision_needed' => 'اعتماد شراء سماعات بديلة بمبلغ ٢٬٤٠٠ ريال.',
                'date' => now()->subDays(21),
            ],
            [
                'project' => 'مشروع إتقان — حلقات جمعية الخرج',
                'author' => $projectManager,
                'done' => 'إجراء القياس البعدي لعينة من ٣٠ طالباً وتحسن المتوسط من ٤٨٪ إلى ٨٢٪.',
                'next' => 'إعداد تقرير منتصف المشروع وتسليمه لمنسقة الجهة.',
                'blockers' => 'غياب متكرر لأربعة طلاب في الحلقة الثالثة.',
                'decision_needed' => null,
                'date' => now()->subDays(7),
            ],
            [
                'project' => 'مشروع القادة الصغار — مدارس الرياض',
                'author' => $executive,
                'done' => 'استكمال التعاقد مع إدارة التعليم وتحديد المدارس الأربع المستهدفة.',
                'next' => 'استلام خطاب تسهيل المهمة وجدولة الزيارة التمهيدية.',
                'blockers' => 'تأخر صدور خطاب تسهيل المهمة يؤخر موعد الانطلاق.',
                'decision_needed' => 'تأجيل الانطلاق أسبوعين أو البدء بمدرستين فقط.',
                'date' => now()->subDays(12),
            ],
            [
                'project' => 'مشروع القادة الصغار — مدارس الرياض',
                'author' => $projectManager,
                'done' => 'اعتماد المحتوى التدريبي وطلب طباعة ٤٠٠ حقيبة.',
                'next' => 'استلام الحقائب من المطبعة وتوزيعها على المدارس.',
                'blockers' => 'اعتذار المطبعة عن موعد التسليم الأول.',
                'decision_needed' => null,
                'date' => now()->subDays(4),
            ],
            [
                'project' => 'مشروع المعلم الملهم — النسخة الداخلية',
                'author' => $executive,
                'done' => 'إغلاق المشروع بعد تنفيذ الورش الست وتسليم دليل المعلم والتقرير الختامي.',
                'next' => 'رصد أثر الورش على أداء المعلمين بعد فصل دراسي كامل.',
                'blockers' => null,
                'decision_needed' => null,
                'date' => now()->subDays(20),
            ],
        ];

        foreach ($rows as $row) {
            $project = $this->project($row['project']);

            if (! $project || ! $row['author']) {
                continue;
            }

            ProjectUpdate::firstOrCreate(
                ['project_id' => $project->id, 'done' => $row['done']],
                [
                    'author_id' => $row['author']->id,
                    'next' => $row['next'],
                    'blockers' => $row['blockers'],
                    'decision_needed' => $row['decision_needed'],
                    'date' => $row['date']->toDateString(),
                ],
            );
        }
    }

    /**
     * ثلاثة تقارير أسبوعية بمحتوى مبني على البيانات الفعلية بنفس بنية
     * reports-index.blade (done / overdue / project_status / open_decisions).
     */
    private function seedWeeklyReports(): void
    {
        foreach ([2, 1, 0] as $weeksAgo) {
            $weekEnd = now()->startOfDay()->subWeeks($weeksAgo);
            $weekStart = $weekEnd->copy()->subDays(6);

            $exists = WeeklyReport::query()
                ->whereDate('week_start', $weekStart->toDateString())
                ->whereDate('week_end', $weekEnd->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            WeeklyReport::create([
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'done' => $this->reportDone($weekStart, $weekEnd),
                'overdue' => $this->reportOverdue(),
                'project_status' => $this->reportProjectStatus(),
                'week_spend' => 0,
                'open_decisions' => $this->reportOpenDecisions(),
                'generated_at' => $weekEnd->copy()->setTime(7, 0),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function reportDone(Carbon $weekStart, Carbon $weekEnd): array
    {
        return Task::query()
            ->select(['id', 'title', 'assigned_to', 'completed_at'])
            ->where('status', 'completed')
            ->where(function ($query) use ($weekStart, $weekEnd) {
                $query->whereBetween('completed_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])
                    ->orWhereNull('completed_at');
            })
            ->with('assignee:id,name')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'assignee' => $task->assignee?->name,
                'completed_at' => $task->completed_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function reportOverdue(): array
    {
        return Task::query()
            ->select(['id', 'title', 'assigned_to', 'due_date', 'status'])
            ->overdue()
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'assignee' => $task->assignee?->name,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'status' => $task->status,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function reportProjectStatus(): array
    {
        return Project::query()
            ->select(['id', 'name', 'status'])
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => fn ($query) => $query->where('status', 'completed'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Project $project) {
                $total = (int) $project->total_tasks;
                $completed = (int) $project->completed_tasks;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'completion_percent' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                    'total_tasks' => $total,
                    'completed_tasks' => $completed,
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function reportOpenDecisions(): array
    {
        return MeetingItem::query()
            ->select(['id', 'meeting_id', 'topic', 'decision', 'due_date', 'status'])
            ->whereNotNull('decision')
            ->where('decision', '!=', '')
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn (MeetingItem $item) => [
                'id' => $item->id,
                'topic' => $item->topic,
                'decision' => $item->decision,
                'due_date' => $item->due_date?->format('Y-m-d'),
                'status' => $item->status,
            ])
            ->all();
    }

    /** لقطتان مجمّدتان (شهرية ومؤشرات) بنفس حمولة مركز التقارير وبصمتها. */
    private function seedReportSnapshots(): void
    {
        $admin = $this->user(self::PHONE_ADMIN);
        $service = app(ReportCenterService::class);
        $month = now()->subMonth()->format('Y-m');

        if (! ReportSnapshot::query()->where('kind', ReportSnapshot::KIND_MONTHLY)->where('period', $month)->exists()) {
            $service->snapshot(
                ReportSnapshot::KIND_MONTHLY,
                'التقرير الشهري — '.$month,
                $service->monthly($month),
                $month,
                null,
                $admin,
            );
        }

        if (! ReportSnapshot::query()->where('kind', ReportSnapshot::KIND_KPI)->exists()) {
            $service->snapshot(
                ReportSnapshot::KIND_KPI,
                'مؤشرات الأداء — لقطة تشغيلية',
                $service->kpis(),
                null,
                null,
                $admin,
            );
        }
    }

    /**
     * إشعارات قاعدة بيانات غير مقروءة لحساب المدير عبر فئات الإشعار الفعلية.
     * sendNow يتجاوز الطابور (QUEUE_CONNECTION=database) وقناة database فقط
     * تمنع إرسال أي بريد أثناء التهيئة.
     */
    private function seedNotifications(): void
    {
        $admin = $this->user(self::PHONE_ADMIN);

        if (! $admin) {
            return;
        }

        $assigned = Task::query()
            ->where('assigned_to', $admin->id)
            ->orderBy('id')
            ->limit(2)
            ->get();

        foreach ($assigned as $task) {
            $this->notifyOnce($admin, new TaskAssigned($task), 'task_id', $task->id, TaskAssigned::class);
        }

        $dueSoon = $assigned->last();

        if ($dueSoon) {
            $this->notifyOnce($admin, new TaskDueSoon($dueSoon), 'task_id', $dueSoon->id, TaskDueSoon::class);
        }

        $escalated = Task::query()
            ->overdue()
            ->where('assigned_by', $admin->id)
            ->orderBy('due_date')
            ->first();

        if ($escalated) {
            $this->notifyOnce(
                $admin,
                new TaskOverdue($escalated, true),
                'task_id',
                $escalated->id,
                TaskOverdue::class,
            );
        }

        $report = WeeklyReport::query()->orderByDesc('generated_at')->first();

        if ($report) {
            $this->notifyOnce(
                $admin,
                new WeeklyReportGenerated($report),
                'weekly_report_id',
                $report->id,
                WeeklyReportGenerated::class,
            );
        }
    }

    /** لا يعيد الإرسال إذا كان الإشعار نفسه (النوع + معرّف الكيان) موجوداً. */
    private function notifyOnce(
        User $notifiable,
        NotificationContract $notification,
        string $key,
        int $id,
        string $type,
    ): void {
        $exists = $notifiable->notifications()
            ->where('type', $type)
            ->where('data', 'like', '%"'.$key.'":'.$id.'%')
            ->exists();

        if ($exists) {
            return;
        }

        Notification::sendNow($notifiable, $notification, ['database']);
    }
}
