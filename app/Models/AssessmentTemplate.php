<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentTemplate extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'framework_id', 'key', 'name', 'description', 'target_roles', 'time_limit_minutes', 'settings', 'status'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'target_roles' => 'array', 'settings' => 'array'];
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(CompetencyFramework::class, 'framework_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentTemplateItem::class, 'template_id')->orderBy('position');
    }
}
