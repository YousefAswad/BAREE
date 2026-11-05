<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['key', 'group', 'scope', 'label'];

    protected function casts(): array
    {
        return ['label' => 'array'];
    }
}
