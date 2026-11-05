<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

final class DemoTenantSeeder extends Seeder
{
    public function run(TenantContext $context, CreditService $credits): void
    {
        $tenant = Tenant::updateOrCreate(['slug' => 'future-schools'], [
            'name' => 'مجموعة مدارس المستقبل', 'type' => 'network', 'status' => 'active', 'country' => 'PS',
            'settings' => ['privacy' => ['mode' => 'consent', 'protected_names' => []]],
        ]);

        // BAREE staff account (platform owner). Enable 2FA before using it outside local development.
        $staff = User::firstOrNew(['email' => 'staff@baree.test']);
        $staff->forceFill(['name' => 'فريق BAREE', 'password' => 'password', 'tenant_id' => null, 'is_platform_admin' => true, 'status' => 'active', 'email_verified_at' => now()])->save();
        $owner = Role::query()->whereNull('tenant_id')->where('key', 'platform_owner')->firstOrFail();
        RoleAssignment::firstOrCreate(['user_id' => $staff->id, 'role_id' => $owner->id, 'tenant_id' => null]);

        $context->runAs($tenant->id, function () use ($tenant, $credits) {
            $math = Department::firstOrCreate(['name' => 'الرياضيات'], ['kind' => 'academic']);
            $teacher = JobTitle::firstOrCreate(['name' => 'معلّم/ة'], ['role_template_key' => 'teacher']);

            $admin = $this->user($tenant, 'admin@future.test', 'مدير المنصة', null, null);
            $ahmad = $this->user($tenant, 'ahmad@future.test', 'أحمد خليل', $math->id, $teacher->id);

            $this->grant($admin, 'institution_admin', $tenant->id);
            $this->grant($ahmad, 'staff', $tenant->id);

            if ($credits->balance($tenant->id) === 0) {
                $credits->grant($tenant->id, 20000);
            }
        });
    }

    private function user(Tenant $tenant, string $email, string $name, ?int $departmentId, ?int $jobTitleId): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name, 'password' => 'password', 'tenant_id' => $tenant->id, 'status' => 'active',
            'department_id' => $departmentId, 'job_title_id' => $jobTitleId, 'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function grant(User $user, string $roleKey, int $tenantId): void
    {
        $role = Role::query()->whereNull('tenant_id')->where('key', $roleKey)->firstOrFail();
        RoleAssignment::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $tenantId]);
    }
}
