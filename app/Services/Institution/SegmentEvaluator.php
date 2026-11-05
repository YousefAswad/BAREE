<?php

namespace App\Services\Institution;

use App\Models\Segment;
use App\Models\User;
use InvalidArgumentException;

/**
 * Rule engine for dynamic segments ("فئات").
 * {"all":[{"field":"role_template","op":"in","value":["teacher"]},{"field":"grades","op":"overlaps","value":[7,8,9]}]}
 * {"any":[...]} is also supported, and groups can nest.
 */
final class SegmentEvaluator
{
    public const FIELDS = ['department_id', 'campus_id', 'job_title_id', 'role_template', 'grades', 'subjects', 'status', 'level_rank', 'readiness', 'competency'];

    public function matches(User $user, array $rules): bool
    {
        if (isset($rules['all'])) {
            return collect($rules['all'])->every(fn ($r) => $this->matches($user, $r));
        }
        if (isset($rules['any'])) {
            return collect($rules['any'])->contains(fn ($r) => $this->matches($user, $r));
        }

        return $this->condition($user, $rules);
    }

    public function validate(array $rules): void
    {
        $walk = function (array $node) use (&$walk) {
            foreach (['all', 'any'] as $group) {
                if (isset($node[$group])) {
                    array_map($walk, $node[$group]);

                    return;
                }
            }
            if (! in_array($node['field'] ?? null, self::FIELDS, true)) {
                throw new InvalidArgumentException('Unknown segment field.');
            }
            if (! in_array($node['op'] ?? null, ['in', 'not_in', 'eq', 'overlaps', 'gte', 'lte', 'below'], true)) {
                throw new InvalidArgumentException('Unknown segment operator.');
            }
        };
        $walk($rules);
    }

    /** Recompute members of a rule-based segment. Returns member count. */
    public function sync(Segment $segment): int
    {
        if ($segment->mode !== 'rules') {
            return $segment->users()->count();
        }

        $ids = [];
        User::query()->where('tenant_id', $segment->tenant_id)->with(['jobTitle', 'passport.level'])
            ->chunkById(500, function ($users) use ($segment, &$ids) {
                foreach ($users as $user) {
                    if ($this->matches($user, $segment->rules ?? [])) {
                        $ids[] = $user->id;
                    }
                }
            });

        $segment->users()->sync($ids);

        return count($ids);
    }

    private function condition(User $user, array $c): bool
    {
        $actual = match ($c['field']) {
            'role_template' => $user->roleTemplateKey(),
            'grades' => (array) data_get($user->profile, 'grades', []),
            'subjects' => (array) data_get($user->profile, 'subjects', []),
            'level_rank' => $user->passport?->level?->rank,
            'readiness' => $user->passport?->readiness,
            'competency' => data_get($user->passport?->competencies, $c['key'] ?? ''),
            default => $user->{$c['field']},
        };
        $value = $c['value'] ?? null;

        return match ($c['op']) {
            'in' => in_array($actual, (array) $value, false),
            'not_in' => ! in_array($actual, (array) $value, false),
            'eq' => $actual == $value,
            'overlaps' => array_intersect((array) $actual, (array) $value) !== [],
            'gte' => $actual !== null && $actual >= $value,
            'lte' => $actual !== null && $actual <= $value,
            'below' => $actual !== null && $actual < $value,
        };
    }
}
