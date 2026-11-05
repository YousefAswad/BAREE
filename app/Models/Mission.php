<?php

namespace App\Models;

use App\Support\Tenancy\SharedOrTenantOwned;
use Illuminate\Database\Eloquent\Model;

class Mission extends Model
{
    use SharedOrTenantOwned;

    protected $fillable = ['tenant_id', 'key', 'title', 'description', 'agent_id', 'path_id', 'position', 'difficulty', 'steps', 'rubric', 'competency_impact', 'dataset', 'xp', 'minutes', 'status'];

    protected $hidden = ['dataset']; // planted answers stay on the server

    protected function casts(): array
    {
        return ['title' => 'array', 'description' => 'array', 'steps' => 'array', 'rubric' => 'array', 'competency_impact' => 'array', 'dataset' => 'array'];
    }

    /** Practice data shown to the learner, without the planted answers. */
    public function publicDataset(): ?array
    {
        return $this->dataset['table'] ?? null;
    }
}
