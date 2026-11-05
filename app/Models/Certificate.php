<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['serial', 'tenant_id', 'user_id', 'course_id', 'holder_name', 'hours', 'issued_at', 'signature', 'revoked_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'revoked_at' => 'datetime', 'hours' => 'float'];
    }
}
