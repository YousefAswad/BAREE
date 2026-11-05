<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UserAgentPreference extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'agent_id', 'instructions'];
}
