<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTestRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['agent_version_id', 'test_case_id', 'batch', 'status', 'output', 'checks', 'provider', 'latency_ms', 'created_at'];

    protected function casts(): array
    {
        return ['checks' => 'array', 'created_at' => 'datetime'];
    }
}
