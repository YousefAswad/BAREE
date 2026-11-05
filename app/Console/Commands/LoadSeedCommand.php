<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Creates synthetic institutions and users for load tests. Refuses to run in production. */
final class LoadSeedCommand extends Command
{
    protected $signature = 'baree:load-seed {--tenants=20} {--users=200} {--password=LoadTest-2026}';

    protected $description = 'Seed synthetic tenants and users for load testing (non-production only).';

    public function handle(TenantContext $context, CreditService $credits): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $password = Hash::make((string) $this->option('password'));
        $staff = Role::query()->whereNull('tenant_id')->where('key', 'staff')->firstOrFail();
        $emails = [];

        for ($t = 1; $t <= (int) $this->option('tenants'); $t++) {
            $tenant = $context->asPlatform(fn () => Tenant::create(['name' => "Load School {$t}", 'slug' => "load-{$t}-".uniqid(), 'type' => 'school', 'status' => 'active', 'default_locale' => 'ar']));
            $context->runAs($tenant->id, function () use ($tenant, $password, $staff, $credits, $t, &$emails) {
                $department = Department::create(['name' => 'Math', 'kind' => 'academic']);
                $title = JobTitle::create(['name' => 'Teacher', 'role_template_key' => 'teacher']);
                $credits->grant($tenant->id, 1_000_000, 'load_test');
                for ($u = 1; $u <= (int) $this->option('users'); $u++) {
                    $user = new User;
                    $user->forceFill(['name' => "Teacher {$t}-{$u}", 'email' => "load{$t}-{$u}@load.test", 'password' => $password, 'tenant_id' => $tenant->id,
                        'department_id' => $department->id, 'job_title_id' => $title->id, 'status' => 'active', 'email_verified_at' => now()])->save();
                    RoleAssignment::create(['user_id' => $user->id, 'role_id' => $staff->id, 'tenant_id' => $tenant->id]);
                    $emails[] = $user->email;
                }
            });
            $this->info("tenant {$t} ready");
        }

        file_put_contents(base_path('load/k6/users.json'), json_encode($emails));
        $this->info('Wrote load/k6/users.json ('.count($emails).' users). Set AI providers to the fake driver for load runs.');

        return self::SUCCESS;
    }
}
