<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MissionAssignment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'mission_id', 'audience', 'mandatory', 'due_at', 'assigned_by'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'mandatory' => 'boolean', 'due_at' => 'datetime'];
    }
}
