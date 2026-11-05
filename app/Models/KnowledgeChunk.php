<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    use SharedOrTenantOwned;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'document_id', 'chunk_index', 'content', 'normalized', 'embedding'];

    protected $hidden = ['embedding', 'normalized'];

    protected function casts(): array
    {
        return ['embedding' => 'array'];
    }
}
