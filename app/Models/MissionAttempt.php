<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MissionAttempt extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'mission_id', 'user_id', 'status', 'rubric_scores', 'xp_awarded', 'completed_at', 'step_index', 'transcript', 'score', 'started_at'];

    protected function casts(): array
    {
        return ['rubric_scores' => 'array', 'transcript' => 'array', 'completed_at' => 'datetime', 'started_at' => 'datetime', 'score' => 'float'];
    }

    public function mission(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}
