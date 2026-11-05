<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PostView extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'user_id', 'post_id', 'viewed_at', 'clicked_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime', 'clicked_at' => 'datetime'];
    }
}
