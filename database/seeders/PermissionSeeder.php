<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

final class PermissionSeeder extends Seeder
{
    /** key => [group, scope, ar, en] */
    public const PERMISSIONS = [
        // Platform (BAREE staff)
        'platform.tenants.manage' => ['platform', 'platform', 'إدارة المؤسسات', 'Manage institutions'],
        'platform.plans.manage' => ['platform', 'platform', 'إدارة الباقات', 'Manage plans'],
        'platform.agents.manage' => ['platform', 'platform', 'إدارة الوكلاء', 'Manage agents'],
        'platform.agents.publish' => ['platform', 'platform', 'نشر إصدارات الوكلاء', 'Publish agent versions'],
        'platform.prompts.manage' => ['platform', 'platform', 'إدارة مكتبة البرومبتات', 'Manage prompt library'],
        'platform.providers.manage' => ['platform', 'platform', 'إدارة مزوّدي AI', 'Manage AI providers'],
        'platform.frameworks.manage' => ['platform', 'platform', 'إدارة الكفاءات والمستويات', 'Manage competencies and levels'],
        'platform.content.publish' => ['platform', 'platform', 'نشر محتوى عام', 'Publish global content'],
        'platform.audit.view' => ['platform', 'platform', 'عرض سجل التدقيق العام', 'View platform audit log'],
        'platform.support.impersonate' => ['platform', 'platform', 'جلسات دعم مؤقتة', 'Time-boxed support sessions'],
        // Institution
        'tenant.settings.manage' => ['institution', 'tenant', 'إعدادات المؤسسة', 'Institution settings'],
        'users.view' => ['people', 'tenant', 'عرض المستخدمين', 'View users'],
        'users.invite' => ['people', 'tenant', 'دعوة مستخدمين', 'Invite users'],
        'users.manage' => ['people', 'tenant', 'إدارة المستخدمين', 'Manage users'],
        'roles.manage' => ['people', 'tenant', 'إدارة الأدوار والصلاحيات', 'Manage roles'],
        'structure.manage' => ['people', 'tenant', 'إدارة الهيكل', 'Manage structure'],
        'segments.manage' => ['people', 'tenant', 'إدارة الفئات', 'Manage segments'],
        'assessments.campaigns.manage' => ['learning', 'tenant', 'إدارة حملات التقييم', 'Manage assessment campaigns'],
        'missions.assign' => ['learning', 'tenant', 'تعيين المهمات', 'Assign missions'],
        'analytics.view_aggregate' => ['analytics', 'tenant', 'تحليلات مجمّعة', 'Aggregate analytics'],
        'analytics.view_individual' => ['analytics', 'tenant', 'نتائج فردية (بموافقة)', 'Individual results (consent-based)'],
        'agents.configure' => ['ai', 'tenant', 'إعداد الوكلاء وقواعد المؤسسة', 'Configure agents and rules'],
        'credits.view' => ['ai', 'tenant', 'عرض الرصيد', 'View credits'],
        'content.publish' => ['content', 'tenant', 'نشر محتوى المؤسسة', 'Publish institution content'],
        // Department-scoped
        'department.analytics.view' => ['analytics', 'department', 'تحليلات القسم', 'Department analytics'],
        'department.missions.assign' => ['learning', 'department', 'تعيين مهمات للقسم', 'Assign missions to department'],
        // Self
        'assessments.take' => ['self', 'self', 'أداء التقييم', 'Take assessments'],
        'ai.agents.use' => ['self', 'self', 'استخدام الوكلاء', 'Use AI agents'],
        'agents.customize_own' => ['self', 'self', 'تخصيص وكلائي', 'Customise my agents'],
        'workspaces.manage_own' => ['self', 'self', 'إدارة مساحات عملي', 'Manage my workspaces'],
        'passport.view_own' => ['self', 'self', 'عرض جوازي', 'View my passport'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $key => [$group, $scope, $ar, $en]) {
            Permission::updateOrCreate(['key' => $key], ['group' => $group, 'scope' => $scope, 'label' => ['ar' => $ar, 'en' => $en]]);
        }
    }
}
