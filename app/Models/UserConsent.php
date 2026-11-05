<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UserConsent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'user_id', 'key', 'granted', 'decided_at'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'decided_at' => 'datetime'];
    }
}
