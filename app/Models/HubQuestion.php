<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class HubQuestion extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'user_id', 'question', 'answer', 'citations', 'answered', 'helpful', 'created_at'];

    protected function casts(): array
    {
        return ['citations' => 'array', 'answered' => 'boolean'];
    }
}
