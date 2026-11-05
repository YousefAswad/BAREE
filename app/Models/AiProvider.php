<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $fillable = ['key', 'driver', 'base_url', 'enabled', 'data_policy'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'data_policy' => 'array'];
    }
}
