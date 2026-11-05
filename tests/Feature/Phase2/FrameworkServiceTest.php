<?php

use App\Models\CompetencyFramework;
use App\Models\Passport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\FrameworkService;
use Database\Seeders\BareeSeeder;

beforeEach(function () {
    $this->seed(BareeSeeder::class);
    $this->framework = bareeTenantContext()->asPlatform(fn () => CompetencyFramework::where('key', 'baree_ai_readiness')->with(['competencies', 'levels'])->first());
    $this->weights = $this->framework->competencies->pluck('weight', 'key')->map(fn ($w) => (float) $w)->all();
    $this->levels = $this->framework->levels->map(fn ($l) => ['rank' => $l->rank, 'min_score' => (float) $l->min_score, 'requirements' => $l->requirements])->all();

    $tenant = Tenant::factory()->create();
    bareeTenantContext()->runAs($tenant->id, function () use ($tenant) {
        foreach ([55, 60, 70] as $score) {
            $u = User::factory()->forTenant($tenant)->create();
            Passport::create(['user_id' => $u->id, 'readiness' => $score, 'competencies' => collect($this->weights)->map(fn () => $score)->all()]);
        }
    });
});

it('rejects thresholds that do not increase and weights that do not match', function () {
    $service = app(FrameworkService::class);
    $bad = $this->levels;
    $bad[3]['min_score'] = 40;

    expect(fn () => $service->preview($this->framework, $this->weights, $bad))->toThrow(DomainException::class)
        ->and(fn () => $service->preview($this->framework, ['prompting' => 10], $this->levels))->toThrow(DomainException::class);
});

it('previews how many existing people move between levels', function () {
    $service = app(FrameworkService::class);
    $stricter = $this->levels;
    $stricter[3]['min_score'] = 75; // creator now needs 75 instead of 65

    $preview = $service->preview($this->framework, $this->weights, $stricter);

    expect($preview['sample'])->toBeGreaterThanOrEqual(3)
        ->and($preview['proposed'][4])->toBeLessThan($preview['current'][4]);
});
