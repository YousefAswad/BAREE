<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ProtectedTerm extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'term', 'term_hash', 'source'];

    protected $hidden = ['term', 'term_hash'];

    protected function casts(): array
    {
        return ['term' => 'encrypted'];
    }

    public static function hashFor(string $term): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($term)), (string) config('app.key'));
    }
}
