<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'assigned_by', 'mandatory', 'due_at', 'progress', 'completed_at'];

    protected function casts(): array
    {
        return ['mandatory' => 'boolean', 'due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function course(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
