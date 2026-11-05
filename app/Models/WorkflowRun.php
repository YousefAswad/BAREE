<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRun extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['tenant_id', 'user_id', 'agent_version_id', 'workspace_id', 'status', 'inputs', 'task_instructions', 'file_ids', 'steps'];

    protected function casts(): array
    {
        return ['inputs' => 'array', 'file_ids' => 'array', 'steps' => 'array'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AgentVersion::class, 'agent_version_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
