<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'city'];
}
