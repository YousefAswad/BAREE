<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AiRun extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['tenant_id', 'user_id', 'agent_version_id', 'workspace_id', 'provider', 'model', 'status', 'inputs', 'output', 'redaction_map', 'redactions_count', 'input_tokens', 'output_tokens', 'credits', 'latency_ms', 'attempts', 'error', 'rating', 'workflow_run_id', 'step_key'];

    protected $hidden = ['redaction_map', 'output'];

    protected function casts(): array
    {
        return ['inputs' => 'array', 'attempts' => 'array', 'redaction_map' => 'encrypted:array'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
