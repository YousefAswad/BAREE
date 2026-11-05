<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'owner_id', 'parent_id', 'kind', 'title', 'instructions', 'memory', 'archived_at'];

    protected function casts(): array
    {
        return ['memory' => 'array', 'archived_at' => 'datetime'];
    }
}
