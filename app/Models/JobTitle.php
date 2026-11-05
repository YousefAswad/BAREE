<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'role_template_key'];
}
