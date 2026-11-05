<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReadinessSnapshot extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'department_id', 'month', 'people', 'readiness', 'levels'];

    protected function casts(): array
    {
        return ['levels' => 'array', 'month' => 'date', 'readiness' => 'float'];
    }
}
