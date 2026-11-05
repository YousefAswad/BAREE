<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentVersion extends Model
{
    protected $fillable = ['agent_id', 'version', 'system_prompt', 'prompt_block_keys', 'input_schema', 'output_schema', 'routing', 'customization_policy', 'credits_cost', 'status', 'created_by', 'published_at', 'rollout', 'release_notes', 'workflow'];

    protected $hidden = ['system_prompt']; // layer 1 is BAREE IP; never serialised to institutions

    protected function casts(): array
    {
        return [
            'prompt_block_keys' => 'array', 'input_schema' => 'array', 'output_schema' => 'array',
            'routing' => 'array', 'customization_policy' => 'array', 'published_at' => 'datetime', 'rollout' => 'array', 'workflow' => 'array',
        ];
    }
}
