<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = ['framework_id', 'rank', 'key', 'name', 'min_score', 'color', 'requirements'];

    protected function casts(): array
    {
        return ['name' => 'array', 'requirements' => 'array', 'min_score' => 'float'];
    }
}
