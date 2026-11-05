<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Output extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'workspace_id', 'ai_run_id', 'parent_id', 'type', 'title', 'content', 'file_path', 'version', 'created_by', 'metadata', 'archived_at'];

    protected function casts(): array
    {
        return ['content' => 'array', 'metadata' => 'array', 'archived_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Root of the version chain, used to list every version of one item. */
    public function rootId(): int
    {
        return (int) ($this->metadata['root_id'] ?? $this->id);
    }
}
