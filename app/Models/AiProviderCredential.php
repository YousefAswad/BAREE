<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderCredential extends Model
{
    protected $fillable = ['provider_id', 'tenant_id', 'secret', 'label', 'rotated_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'rotated_at' => 'datetime'];
    }
}
