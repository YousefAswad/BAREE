<?php

namespace Database\Seeders;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/** Call from DatabaseSeeder: $this->call(BareeSeeder::class); */
final class BareeSeeder extends Seeder
{
    public function run(TenantContext $context): void
    {
        $context->asPlatform(fn () => $this->call([
            PermissionSeeder::class,
            PlanSeeder::class,
            RoleTemplateSeeder::class,
            CompetencyFrameworkSeeder::class,
            AssessmentSeeder::class,
            AiSeeder::class,
            AgentTestCaseSeeder::class,
            StudioSeeder::class,
            LearningSeeder::class,
            DemoTenantSeeder::class,
        ]));
    }
}
