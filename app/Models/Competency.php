<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    protected $fillable = ['framework_id', 'key', 'name', 'description', 'weight', 'position'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'weight' => 'float'];
    }
}
