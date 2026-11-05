<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;

class KnowledgeDocument extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'kind', 'title', 'body', 'source_file_id', 'audience', 'status', 'version', 'created_by', 'published_at'];

    protected function casts(): array
    {
        return ['title' => 'array', 'audience' => 'array', 'published_at' => 'datetime'];
    }

    public function chunks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id');
    }
}
