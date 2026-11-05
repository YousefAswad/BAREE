<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;

class LearningPath extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'key', 'title', 'description', 'role_templates', 'badge_key', 'status'];

    protected function casts(): array
    {
        return ['title' => 'array', 'description' => 'array', 'role_templates' => 'array'];
    }

    public function missions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Mission::class, 'path_id')->orderBy('position');
    }
}
