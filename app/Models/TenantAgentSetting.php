<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantAgentSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'agent_id', 'enabled', 'department_ids', 'institution_rules', 'monthly_credit_cap'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'department_ids' => 'array'];
    }
}
