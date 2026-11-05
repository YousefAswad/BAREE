<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passport extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'readiness', 'level_id', 'competencies', 'next_level', 'xp', 'refreshed_at'];

    protected function casts(): array
    {
        return ['competencies' => 'array', 'next_level' => 'array', 'readiness' => 'float', 'refreshed_at' => 'datetime'];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
