<?php

namespace App\Domain\Assessment;

final readonly class ScoringResult
{
    public function __construct(
        public float $overall,
        public array $competencies,
        public ?int $levelId,
        public ?string $levelKey,
        public ?array $next,
        public array $calibration,
    ) {}

    public function toArray(): array
    {
        return [
            'overall' => $this->overall,
            'competencies' => $this->competencies,
            'level_id' => $this->levelId,
            'level_key' => $this->levelKey,
            'next' => $this->next,
            'calibration' => $this->calibration,
        ];
    }
}
