<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class CourseModule extends Model
{
    public $timestamps = false;

    protected $fillable = ['course_id', 'position', 'title'];

    protected function casts(): array
    {
        return ['title' => 'array'];
    }

    public function lessons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CourseLesson::class, 'module_id')->orderBy('position');
    }
}
