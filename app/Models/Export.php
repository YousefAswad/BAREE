<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['tenant_id', 'user_id', 'output_id', 'format', 'status', 'disk', 'path', 'error', 'expires_at'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
