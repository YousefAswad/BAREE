<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['question_id', 'position', 'label', 'score', 'feedback'];

    protected $hidden = ['score']; // the correct answer never leaves the server

    protected function casts(): array
    {
        return ['label' => 'array', 'feedback' => 'array', 'score' => 'integer'];
    }
}
