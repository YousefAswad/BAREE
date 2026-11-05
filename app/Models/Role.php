<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Not tenant-scoped on purpose: templates (tenant_id = null) must be readable by every institution. */
class Role extends Model
{
    protected $fillable = ['tenant_id', 'key', 'name', 'level', 'is_template', 'is_system', 'cloned_from_id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_template' => 'boolean', 'is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
