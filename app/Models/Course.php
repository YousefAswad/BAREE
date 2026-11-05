<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'key', 'title', 'description', 'role_templates', 'hours', 'certificate', 'badge_key', 'status'];

    protected function casts(): array
    {
        return ['title' => 'array', 'description' => 'array', 'role_templates' => 'array', 'certificate' => 'boolean', 'hours' => 'float'];
    }

    public function modules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    public function lessons(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(CourseLesson::class, CourseModule::class, 'course_id', 'module_id');
    }
}
