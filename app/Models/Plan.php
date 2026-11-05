<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['key', 'name', 'limits', 'active', 'price_per_seat_cents', 'currency', 'interval'];

    protected function casts(): array
    {
        return ['name' => 'array', 'limits' => 'array', 'active' => 'boolean'];
    }
}
