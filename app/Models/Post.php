<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'kind', 'title', 'body', 'targeting', 'published_at', 'author_id', 'status', 'cta_url', 'pinned_until'];

    protected function casts(): array
    {
        return ['title' => 'array', 'body' => 'array', 'targeting' => 'array', 'published_at' => 'datetime', 'pinned_until' => 'datetime'];
    }
}
