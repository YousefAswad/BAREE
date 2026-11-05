<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'lesson_progress';

    protected $fillable = ['tenant_id', 'user_id', 'lesson_id', 'score', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'score' => 'float'];
    }
}
