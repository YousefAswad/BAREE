<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class RoleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $self = ['assessments.take', 'ai.agents.use', 'agents.customize_own', 'workspaces.manage_own', 'passport.view_own'];

        $templates = [
            // key => [level, ar, en, permissions, is_template]
            'platform_owner' => ['platform', 'مالك المنصة', 'Platform owner', array_keys(array_filter(PermissionSeeder::PERMISSIONS, fn ($p) => $p[1] === 'platform')), false],
            'platform_agent_manager' => ['platform', 'مدير الوكلاء', 'Agent manager', ['platform.agents.manage', 'platform.agents.publish', 'platform.prompts.manage', 'platform.providers.manage'], false],
            'platform_support' => ['platform', 'الدعم الفني', 'Support', ['platform.tenants.manage', 'platform.support.impersonate'], false],
            'institution_owner' => ['tenant', 'مالك المؤسسة', 'Institution owner', array_merge($self, ['tenant.settings.manage', 'users.view', 'users.invite', 'users.manage', 'roles.manage', 'structure.manage', 'segments.manage', 'assessments.campaigns.manage', 'missions.assign', 'analytics.view_aggregate', 'analytics.view_individual', 'agents.configure', 'credits.view', 'content.publish']), true],
            'institution_admin' => ['tenant', 'مدير المنصة بالمؤسسة', 'Institution admin', array_merge($self, ['users.view', 'users.invite', 'users.manage', 'structure.manage', 'segments.manage', 'assessments.campaigns.manage', 'missions.assign', 'analytics.view_aggregate', 'agents.configure', 'credits.view', 'content.publish']), true],
            'department_head' => ['department', 'رئيس قسم', 'Department head', array_merge($self, ['department.analytics.view', 'department.missions.assign']), true],
            'trainer' => ['tenant', 'مدرّب', 'Trainer', array_merge($self, ['missions.assign', 'content.publish']), true],
            'staff' => ['tenant', 'موظف', 'Staff member', $self, true],
            'viewer' => ['tenant', 'مشاهد', 'Viewer', ['passport.view_own'], true],
        ];

        foreach ($templates as $key => [$level, $ar, $en, $permissions, $isTemplate]) {
            $role = Role::updateOrCreate(['tenant_id' => null, 'key' => $key], [
                'name' => ['ar' => $ar, 'en' => $en], 'level' => $level, 'is_template' => $isTemplate, 'is_system' => true,
            ]);
            $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id'));
        }
    }
}
