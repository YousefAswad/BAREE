<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptBlock extends Model
{
    protected $fillable = ['key', 'version', 'group', 'content', 'status'];
}
