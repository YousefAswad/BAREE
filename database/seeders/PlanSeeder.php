<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['starter', 'Starter', ['users' => 200, 'monthly_credits' => 20000, 'storage_gb' => 50, 'custom_agents' => false, 'media' => false]],
            ['professional', 'Professional', ['users' => 500, 'monthly_credits' => 80000, 'storage_gb' => 200, 'custom_agents' => false, 'media' => true]],
            ['institution', 'Institution', ['users' => 2000, 'monthly_credits' => 200000, 'storage_gb' => 500, 'custom_agents' => true, 'media' => true]],
            ['enterprise', 'Enterprise', ['users' => null, 'monthly_credits' => null, 'storage_gb' => null, 'custom_agents' => true, 'media' => true, 'api' => true]],
        ];

        foreach ($plans as [$key, $name, $limits]) {
            Plan::updateOrCreate(['key' => $key], ['name' => ['ar' => $name, 'en' => $name], 'limits' => $limits, 'active' => true]);
        }
    }
}
