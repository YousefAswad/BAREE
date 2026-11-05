<?php

require_once __DIR__.'/Phase4Helpers.php';

use App\Models\HubQuestion;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Services\Knowledge\HubService;
use App\Services\Knowledge\KnowledgeIndexer;
use App\Services\Knowledge\KnowledgeSearch;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->ahmad = learningSetup();
    $policy = KnowledgeDocument::create(['kind' => 'policy', 'title' => ['ar' => 'سياسة استخدام الذكاء الاصطناعي'], 'status' => 'indexing',
        'body' => "## البند ٣: بيانات الطلاب\n\nمسموح تحليل نتائج الطلاب داخل المنصة المعتمدة فقط. ممنوع رفع بيانات تعرّف بالطالب على أدوات خارجية."]);
    app(KnowledgeIndexer::class)->index($policy);

    $this->other = Tenant::factory()->create();
    bareeTenantContext()->runAs($this->other->id, function () {
        $secret = KnowledgeDocument::create(['kind' => 'policy', 'title' => ['ar' => 'سياسة مؤسسة ثانية'], 'status' => 'indexing',
            'body' => 'بيانات الطلاب مسموح رفعها على أي أداة خارجية SECRET_OTHER_TENANT']);
        app(KnowledgeIndexer::class)->index($secret);
    });
    bareeTenantContext()->set($this->ahmad->tenant_id);
});

it('retrieves only this institution\'s and BAREE\'s documents', function () {
    $hits = app(KnowledgeSearch::class)->search($this->ahmad->tenant_id, 'teacher', 'هل بقدر أرفع بيانات الطلاب على أداة خارجية؟');

    expect($hits)->not->toBeEmpty()
        ->and(collect($hits)->pluck('content')->implode(' '))->not->toContain('SECRET_OTHER_TENANT')
        ->and($hits[0]['title']['ar'])->toBe('سياسة استخدام الذكاء الاصطناعي');
});

it('answers with numbered citations from the approved documents', function () {
    claudeJson('ممنوع رفع بيانات تعرّف بالطالب على أدوات خارجية [1].');

    $result = app(HubService::class)->ask($this->ahmad, 'هل بقدر أرفع بيانات الطلاب على أداة خارجية؟');

    expect($result['answered'])->toBeTrue()->and($result['citations'][0]['n'])->toBe(1)
        ->and(HubQuestion::latest('id')->first()->answered)->toBeTrue();
    Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'البند'));
});

it('says it does not know instead of guessing, without calling any model', function () {
    Http::fake();

    $result = app(HubService::class)->ask($this->ahmad, 'كم سعر تذكرة الطيران لباريس؟');

    expect($result['answered'])->toBeFalse()->and($result['citations'])->toBe([]);
    Http::assertNothingSent();
});

it('respects document audiences', function () {
    $hrOnly = KnowledgeDocument::create(['kind' => 'policy', 'title' => ['ar' => 'سياسة الرواتب'], 'audience' => ['hr'], 'status' => 'indexing', 'body' => 'سلّم الرواتب والعلاوات السنوية للموظفين']);
    app(KnowledgeIndexer::class)->index($hrOnly);

    $hits = app(KnowledgeSearch::class)->search($this->ahmad->tenant_id, 'teacher', 'سلّم الرواتب والعلاوات');

    expect(collect($hits)->pluck('document_id'))->not->toContain($hrOnly->id);
});
