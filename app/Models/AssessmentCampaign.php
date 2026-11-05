<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentCampaign extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'template_id', 'name', 'audience', 'starts_at', 'ends_at', 'status'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class, 'template_id');
    }
}
