<?php

namespace App\Services\Institution;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves an audience definition to active users of the current tenant.
 * Inside one key it is OR (math OR science); across keys it is AND (math AND teachers).
 * {"departments":[1,2],"segments":[5],"role_templates":["teacher"],"campuses":[3]} — empty = whole institution.
 */
final class AudienceResolver
{
    public function query(array $audience, int $tenantId): Builder
    {
        return User::query()
            ->where('tenant_id', $tenantId) // users are not globally scoped; be explicit
            ->where('status', 'active')
            ->when($audience['departments'] ?? null, fn ($q, $ids) => $q->whereIn('department_id', $ids))
            ->when($audience['campuses'] ?? null, fn ($q, $ids) => $q->whereIn('campus_id', $ids))
            ->when($audience['segments'] ?? null, fn ($q, $ids) => $q->whereHas('segments', fn ($s) => $s->whereIn('segments.id', $ids)))
            ->when($audience['role_templates'] ?? null, fn ($q, $keys) => $q->whereHas('jobTitle', fn ($j) => $j->whereIn('role_template_key', $keys)));
    }

    public function count(array $audience, int $tenantId): int
    {
        return $this->query($audience, $tenantId)->count();
    }
}
