<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OutputFeedback extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'output_feedback';

    protected $fillable = ['tenant_id', 'user_id', 'ai_run_id', 'agent_id', 'rating', 'reasons', 'comment', 'edit_distance', 'share_with_baree', 'created_at'];

    protected function casts(): array
    {
        return ['reasons' => 'array', 'share_with_baree' => 'boolean'];
    }
}
