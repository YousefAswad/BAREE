<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UserImport extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'uploaded_by', 'path', 'status', 'total', 'valid', 'invited', 'report'];

    protected function casts(): array
    {
        return ['report' => 'array'];
    }
}
