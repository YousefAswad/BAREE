<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyFramework extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'key', 'name', 'version', 'status', 'is_default'];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_default' => 'boolean'];
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(Competency::class, 'framework_id')->orderBy('position');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class, 'framework_id')->orderBy('rank');
    }
}
