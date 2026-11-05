<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'key', 'name', 'description', 'category', 'role_templates', 'status', 'published_version_id', 'content_type', 'availability', 'output_types', 'competency_tags', 'human_review'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'role_templates' => 'array', 'output_types' => 'array', 'competency_tags' => 'array', 'human_review' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class);
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(AgentVersion::class, 'published_version_id');
    }
}
