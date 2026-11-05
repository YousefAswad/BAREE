<?php

use App\Models\User;
use Database\Seeders\BareeSeeder;

function opsSetup(): User
{
    config(['knowledge.embeddings.driver' => 'fake', 'broadcasting.default' => 'null', 'ai_gateway.providers.anthropic.key' => 'test']);
    test()->seed(BareeSeeder::class);
    $ahmad = User::where('email', 'ahmad@future.test')->first();
    bareeTenantContext()->set($ahmad->tenant_id);

    return $ahmad;
}
