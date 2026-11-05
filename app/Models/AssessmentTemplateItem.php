<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentTemplateItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['template_id', 'question_id', 'section', 'position'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
