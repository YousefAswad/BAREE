<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'competency_id', 'type', 'difficulty', 'weight', 'stem', 'scenario', 'explanation', 'audience', 'status', 'version'];

    protected function casts(): array
    {
        return ['stem' => 'array', 'scenario' => 'array', 'explanation' => 'array', 'audience' => 'array', 'weight' => 'float'];
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }
}
