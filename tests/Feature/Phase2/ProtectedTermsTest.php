<?php

use App\AI\Gateway\AiGateway;
use App\Models\ProtectedTerm;
use App\Models\User;
use App\Services\Institution\ProtectedTermsRepository;
use Database\Seeders\BareeSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(BareeSeeder::class);
    $this->ahmad = User::where('email', 'ahmad@future.test')->first();
    bareeTenantContext()->set($this->ahmad->tenant_id);
});

it('stores roster names encrypted and de-duplicates them', function () {
    $added = app(ProtectedTermsRepository::class)->importRoster("\xEF\xBB\xBFرهف\nيوسف\nرهف\n");

    expect($added)->toBe(2)
        ->and(DB::table('protected_terms')->pluck('term')->contains('رهف'))->toBeFalse() // ciphertext at rest
        ->and(ProtectedTerm::count())->toBe(2);
});

it('keeps roster names out of every provider request', function () {
    config(['ai_gateway.providers.anthropic.key' => 'test']);
    app(ProtectedTermsRepository::class)->add(['رهف']);
    Http::fake(['api.anthropic.com/*' => Http::response(['model' => 'm', 'content' => [['type' => 'text', 'text' => 'ok']], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])]);

    app(AiGateway::class)->run($this->ahmad, 'lesson_pack', ['grade' => 'الرابع', 'subject' => 'رياضيات', 'topic' => 'خطة دعم لرهف', 'duration' => 45, 'student_level' => 'mixed']);

    Http::assertSent(fn (Request $r) => ! str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'رهف'));
});
