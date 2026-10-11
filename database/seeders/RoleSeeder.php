<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The 6 system roles and their default permission matrix (PLAN.md § 2).
 * The 9 permissions the published matrix left blank were agreed in-session;
 * an admin can change any of this from /admin/roles without touching code.
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, array{name: string, permissions: array<int, string>|string}> */
    private const ROLES = [
        'admin' => [
            'name' => 'مدير النظام',
            'permissions' => '*',
        ],
        'manager' => [
            'name' => 'مدير الفريق',
            'permissions' => [
                'tickets.view.all', 'tickets.view.assigned', 'tickets.view.own',
                'tickets.create', 'tickets.edit', 'tickets.assign', 'tickets.resolve',
                'tickets.reopen', 'tickets.close', 'tickets.notify_client',
                'comments.create', 'comments.internal',
                // ★ (2026-08-05) subtasks.schedule stops here and at admin: a
                // due date now decides whether its owner earns or loses the
                // subtask's points, so it belongs to whoever plans the work.
                'subtasks.manage', 'subtasks.reassign', 'subtasks.schedule',
                'time.log', 'links.manage',
                'ratings.give', 'ratings.view.all',
                'points.view.own', 'points.view.all',
                'reports.view',
                // ★ (2026-08-29) F27. The manager gets the audit screen as
                // well as the tab: "which resolved tickets have no code behind
                // them" is a question about the team, and the team is theirs.
                'github.view', 'github.audit',
            ],
        ],
        'support' => [
            'name' => 'دعم فني',
            'permissions' => [
                'tickets.view.all', 'tickets.view.assigned', 'tickets.view.own',
                'tickets.create', 'tickets.edit', 'tickets.resolve', 'tickets.close',
                'tickets.notify_client',
                'comments.create', 'comments.internal',
                'subtasks.manage', 'subtasks.reassign', 'time.log', 'links.manage',
                'points.view.own', 'github.view',
            ],
        ],
        'frontend' => [
            'name' => 'مطوّر الواجهات الأمامية',
            'permissions' => [
                'tickets.view.assigned',
                'comments.create', 'comments.internal',
                'worklog.manage', 'subtasks.manage', 'subtasks.reassign', 'time.log', 'links.manage',
                'points.view.own', 'github.view',
            ],
        ],
        'backend' => [
            'name' => 'مطوّر الأنظمة الخلفية',
            'permissions' => [
                'tickets.view.assigned',
                'comments.create', 'comments.internal',
                'worklog.manage', 'subtasks.manage', 'subtasks.reassign', 'time.log', 'links.manage',
                'points.view.own', 'github.view',
            ],
        ],
        'devops' => [
            'name' => 'عمليات التطوير',
            // Same set as a developer: DevOps holds no work log, but everything
            // else about being on a ticket is identical — see it, comment,
            // manage its subtasks, log time, earn on it.
            'permissions' => [
                'tickets.view.assigned',
                'comments.create', 'comments.internal',
                'subtasks.manage', 'subtasks.reassign', 'time.log', 'links.manage',
                'points.view.own', 'github.view',
            ],
        ],
        'tester' => [
            'name' => 'المختبِر',
            'permissions' => [
                'tickets.view.assigned', 'tickets.resolve', 'tickets.reopen',
                'comments.create', 'comments.internal',
                'subtasks.manage', 'subtasks.reassign', 'time.log',
                'points.view.own', 'github.view',
            ],
        ],
    ];

    /**
     * F06 role-assignment extension: roles that get their own dropdown in the
     * ticket assignment panel out of the box. Distribution is now fully
     * role-based — there are no hardcoded assignment columns anymore, so the
     * four dev roles (frontend/backend/tester/devops) are assignable through the
     * same panel as everyone else. A plain role attribute
     * (Role::assignable_on_tickets), not a permission.
     */
    private const ASSIGNABLE_ON_TICKETS = [
        'admin', 'manager', 'support', 'frontend', 'backend', 'devops', 'tester',
    ];

    /**
     * Roles whose assignment gets a بدأت/خلصت work log (F07). Replaces the old
     * hardcoded "the frontend and backend columns log work" rule.
     */
    private const LOGS_WORK = ['frontend', 'backend'];

    /** Roles that put a ticket in the testing queue (F16). Was the tester_id column. */
    private const IS_TESTER = ['tester'];

    /**
     * English name and both descriptions per role. Kept here, not only in the
     * backfill migration, because on a fresh install that migration runs
     * before any role exists and so fills nothing.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const TEXTS = [
        'admin' => ['System administrator', 'يدير إعدادات النظام والمستخدمين والصلاحيات؛ ليس جهة تنفيذ تلقائيًا.', 'Manages system settings, users and permissions; not a delivery owner by default.'],
        'manager' => ['Team manager', 'يخطط العمل ويوزّعه ويتابع الأداء والاعتمادات.', 'Plans and assigns work, and follows up on performance and approvals.'],
        'support' => ['Customer support', 'يستقبل طلب العميل ويتابعه ويبلّغه بالنتيجة.', 'Receives the client request, follows it up and reports the outcome.'],
        'frontend' => ['Frontend developer', 'ينفذ واجهات وتجربة المستخدم الخاصة بالتذكرة.', 'Delivers the interface and user experience work on the ticket.'],
        'backend' => ['Backend developer', 'ينفذ منطق الخادم والبيانات والتكاملات.', 'Delivers server logic, data and integrations.'],
        'devops' => ['DevOps engineer', 'يتولى النشر والبنية التحتية والعمليات التشغيلية.', 'Owns deployment, infrastructure and operations.'],
        'tester' => ['Quality assurance', 'يتحقق من النتيجة ويعتمد الحل أو يعيده للتنفيذ.', 'Verifies the result and approves the fix or returns it to delivery.'],
    ];

    public function run(): void
    {
        $permissionIds = Permission::pluck('id', 'key');

        foreach (self::ROLES as $key => $definition) {
            $role = Role::updateOrCreate(
                ['key' => $key],
                [
                    'name_ar' => $definition['name'],
                    'name_en' => self::TEXTS[$key][0],
                    'description_ar' => self::TEXTS[$key][1],
                    'description_en' => self::TEXTS[$key][2],
                    'is_system' => true,
                    'assignable_on_tickets' => in_array($key, self::ASSIGNABLE_ON_TICKETS, true),
                    'logs_work' => in_array($key, self::LOGS_WORK, true),
                    'is_tester' => in_array($key, self::IS_TESTER, true),
                ]
            );

            $ids = $definition['permissions'] === '*'
                ? $permissionIds->values()
                : $permissionIds->only($definition['permissions'])->values();

            $role->syncPermissions($ids->all());
        }
    }
}
