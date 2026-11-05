<?php

use App\Models\User;
use Database\Seeders\BareeSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['knowledge.embeddings.driver' => 'fake', 'broadcasting.default' => 'null']);
    $this->seed(BareeSeeder::class);
    $this->teacher = User::where('email', 'ahmad@future.test')->first();
    $this->admin = User::where('email', 'admin@future.test')->first();
    $this->staff = User::where('email', 'staff@baree.test')->first();
});

function parameterlessGetRoutes(string $prefix): array
{
    return collect(Route::getRoutes())->filter(fn ($r) => in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), $prefix) && ! str_contains($r->uri(), '{'))
        ->map(fn ($r) => '/'.$r->uri())->values()->all();
}

it('requires sign-in for every admin and platform page', function () {
    foreach (array_merge(parameterlessGetRoutes('admin'), parameterlessGetRoutes('platform')) as $uri) {
        $status = $this->get($uri)->getStatusCode();
        expect(in_array($status, [302, 401, 404], true))->toBeTrue("{$uri} answered {$status} to a guest");
    }
});

it('hides every platform page from institution users and every admin page from teachers', function () {
    foreach (parameterlessGetRoutes('platform') as $uri) {
        expect($this->actingAs($this->admin)->get($uri)->getStatusCode())->toBe(404, "{$uri} visible to an institution admin");
    }
    foreach (parameterlessGetRoutes('admin') as $uri) {
        expect(in_array($this->actingAs($this->teacher)->get($uri)->getStatusCode(), [403, 404], true))->toBeTrue("{$uri} open to a teacher");
    }
});

it('never sends secrets or internal fields to the browser', function () {
    $forbidden = ['token_hash', 'redaction_map', 'two_factor_secret', 'two_factor_recovery_codes', 'phone_hash', 'otp_hash', '"password"', 'api_key', 'remember_token', '"answers"'];
    $visits = [
        [$this->teacher, ['/home', '/studio', '/studio/tools/worksheet', '/workspaces', '/lab', '/lab/missions/exam_error_hunt', '/academy', '/academy/ai_for_teachers', '/hub', '/passport', '/notifications', '/settings/privacy', '/settings/notifications']],
        [$this->admin, ['/admin', '/admin/users', '/admin/segments', '/admin/campaigns', '/admin/agents', '/admin/analytics', '/admin/assignments']],
    ];

    foreach ($visits as [$user, $uris]) {
        foreach ($uris as $uri) {
            $response = $this->actingAs($user)->get($uri);
            expect($response->getStatusCode())->toBe(200, "{$uri} failed");
            $json = json_encode($response->viewData('page')['props'] ?? []);
            foreach ($forbidden as $needle) {
                expect(str_contains($json, $needle))->toBeFalse("{$uri} leaked {$needle}");
            }
            expect($json)->not->toContain('system_prompt'); // layer 1 is visible to BAREE staff only
        }
    }
});

it('sends security headers and a request id', function () {
    $response = $this->actingAs($this->teacher)->get('/home');

    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});

it('protects the deep health check with a token', function () {
    config(['baree.health_token' => 'secret-token', 'baree.uploads.scan_with' => 'none']);

    $this->get('/health/deep')->assertNotFound();
    $this->withToken('secret-token')->get('/health/deep')->assertJsonStructure(['healthy', 'checks' => ['database', 'cache', 'storage']]);
});
