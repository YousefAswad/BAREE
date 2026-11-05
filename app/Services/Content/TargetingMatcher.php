<?php

namespace App\Services\Content;

use App\Models\User;

/**
 * One definition of "who should see this", shared by the feed and the audience estimate.
 * {"roles":["teacher"],"levels":[1,2,3],"gap":{"competency":"responsible_ai","below":60},"departments":[3],"segments":[5],"campuses":[1]}
 * Empty targeting = everyone in scope. Inside a key: OR. Across keys: AND.
 */
final class TargetingMatcher
{
    public function matches(User $user, ?array $targeting): bool
    {
        $t = $targeting ?? [];

        if (! empty($t['roles']) && ! in_array($user->roleTemplateKey(), $t['roles'], true)) {
            return false;
        }
        if (! empty($t['departments']) && ! in_array($user->department_id, $t['departments'], true)) {
            return false;
        }
        if (! empty($t['campuses']) && ! in_array($user->campus_id, $t['campuses'], true)) {
            return false;
        }
        if (! empty($t['segments']) && ! $user->segments()->whereIn('segments.id', $t['segments'])->exists()) {
            return false;
        }

        $passport = $user->passport()->with('level')->first();
        if (! empty($t['levels']) && ! in_array($passport?->level?->rank, $t['levels'], true)) {
            return false;
        }
        if (! empty($t['gap']['competency'])) {
            $value = data_get($passport?->competencies, $t['gap']['competency']);
            if ($value === null || $value >= (float) ($t['gap']['below'] ?? 0)) {
                return false;
            }
        }

        return true;
    }
}
