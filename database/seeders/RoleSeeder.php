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
            'name' => 'مدير التيم',
            'permissions' => [
                'tickets.view.all', 'tickets.view.assigned', 'tickets.view.own',
                'tickets.create', 'tickets.edit', 'tickets.assign', 'tickets.resolve',
                'tickets.reopen', 'tickets.close', 'tickets.notify_client',
                'comments.create', 'comments.internal',
                'subtasks.manage', 'time.log', 'links.manage',
                'ratings.give', 'ratings.view.all',
                'points.view.own', 'points.view.all',
                'reports.view',
            ],
        ],
        'support' => [
            'name' => 'دعم فني',
            'permissions' => [
                'tickets.view.all', 'tickets.view.assigned', 'tickets.view.own',
                'tickets.create', 'tickets.edit', 'tickets.resolve', 'tickets.close',
                'tickets.notify_client',
                'comments.create', 'comments.internal',
                'subtasks.manage', 'time.log', 'links.manage',
                'points.view.own',
            ],
        ],
        'frontend' => [
            'name' => 'مبرمج فرونت',
            'permissions' => [
                'tickets.view.assigned',
                'comments.create', 'comments.internal',
                'worklog.manage', 'subtasks.manage', 'time.log', 'links.manage',
                'points.view.own',
            ],
        ],
        'backend' => [
            'name' => 'مبرمج باك',
            'permissions' => [
                'tickets.view.assigned',
                'comments.create', 'comments.internal',
                'worklog.manage', 'subtasks.manage', 'time.log', 'links.manage',
                'points.view.own',
            ],
        ],
        'tester' => [
            'name' => 'تيستر',
            'permissions' => [
                'tickets.view.assigned', 'tickets.resolve', 'tickets.reopen',
                'comments.create', 'comments.internal',
                'subtasks.manage', 'time.log',
                'points.view.own',
            ],
        ],
    ];

    public function run(): void
    {
        $permissionIds = Permission::pluck('id', 'key');

        foreach (self::ROLES as $key => $definition) {
            $role = Role::updateOrCreate(
                ['key' => $key],
                ['name_ar' => $definition['name'], 'is_system' => true]
            );

            $ids = $definition['permissions'] === '*'
                ? $permissionIds->values()
                : $permissionIds->only($definition['permissions'])->values();

            $role->syncPermissions($ids->all());
        }
    }
}
