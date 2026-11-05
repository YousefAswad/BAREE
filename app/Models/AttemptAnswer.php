<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'attempt_id', 'question_id', 'option_id', 'response', 'confidence', 'score', 'max_score', 'time_ms'];

    protected function casts(): array
    {
        return ['score' => 'float', 'max_score' => 'float'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
