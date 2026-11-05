<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Files owned by an agent (BAREE, tenant_id null), a workspace, a user, or attached to a single task. */
class KnowledgeFile extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'owner_type', 'owner_id', 'disk', 'path', 'original_name', 'mime', 'size', 'sha256', 'usage_mode', 'status', 'extracted_text', 'text_chars', 'scanned_at', 'rejection_reason', 'uploaded_by'];

    protected $hidden = ['path', 'extracted_text'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isUsable(): bool
    {
        return $this->status === 'indexed' && $this->usage_mode !== 'off';
    }
}
