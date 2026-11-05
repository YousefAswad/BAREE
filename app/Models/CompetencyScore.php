<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CompetencyScore extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'user_id', 'competency_id', 'score', 'source', 'source_id', 'recorded_at'];

    protected function casts(): array
    {
        return ['score' => 'float', 'recorded_at' => 'datetime'];
    }
}
