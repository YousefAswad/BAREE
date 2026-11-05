<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'campus_id', 'parent_id', 'name', 'kind'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
