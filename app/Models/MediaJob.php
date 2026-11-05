<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class MediaJob extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['tenant_id', 'user_id', 'workspace_id', 'output_id', 'type', 'provider', 'status', 'external_id', 'request', 'disk', 'path', 'mime', 'credits', 'polls', 'error'];

    protected $hidden = ['path', 'external_id'];

    protected function casts(): array
    {
        return ['request' => 'array'];
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
