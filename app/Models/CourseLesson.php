<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class CourseLesson extends Model
{
    public $timestamps = false;

    protected $fillable = ['module_id', 'position', 'type', 'title', 'body', 'video_url', 'mission_id', 'quiz', 'minutes'];

    protected $hidden = ['quiz']; // answers never go to the browser; use publicQuiz()

    protected function casts(): array
    {
        return ['title' => 'array', 'quiz' => 'array'];
    }

    public function publicQuiz(): array
    {
        return array_map(fn ($q) => ['q' => $q['q'], 'options' => $q['options']], $this->quiz ?? []);
    }

    public function module(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }
}
