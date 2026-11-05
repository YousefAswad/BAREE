<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = ['key', 'name', 'criteria', 'color'];

    protected function casts(): array
    {
        return ['name' => 'array', 'criteria' => 'array'];
    }
}
