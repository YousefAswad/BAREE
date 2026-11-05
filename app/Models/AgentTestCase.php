<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTestCase extends Model
{
    protected $fillable = ['agent_id', 'name', 'category', 'inputs', 'task_instructions', 'assertions', 'blocking', 'active'];

    protected function casts(): array
    {
        return ['inputs' => 'array', 'assertions' => 'array', 'blocking' => 'boolean', 'active' => 'boolean'];
    }
}
