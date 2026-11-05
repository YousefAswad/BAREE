<?php

namespace App\Services\Knowledge;

use App\AI\Gateway\AiGateway;
use App\AI\Privacy\PiiRedactor;
use App\Models\HubQuestion;
use App\Models\User;
use App\Services\Institution\ProtectedTermsRepository;

/**
 * "Ask about your institution's policies": answers only from approved documents, with numbered citations.
 * When nothing relevant is found, it says so instead of guessing.
 */
final class HubService
{
    public function __construct(private KnowledgeSearch $search, private AiGateway $gateway, private PiiRedactor $redactor, private ProtectedTermsRepository $terms) {}

    public function ask(User $user, string $question): array
    {
        $redacted = $this->redactor->redact(mb_substr($question, 0, 1000), $this->terms->forTenant($user->tenant_id))['text'];
        $hits = $this->search->search($user->tenant_id, $user->roleTemplateKey(), $redacted);
        $relevant = array_values(array_filter($hits, fn ($h) => $h['score'] >= (float) config('knowledge.min_score')));

        if ($relevant === []) {
            $answer = __('ما لقيت إجابة لهالسؤال بالوثائق المعتمدة بمؤسستك. جرّب صياغة ثانية، أو اسأل مسؤول المنصة بمؤسستك.');
            $this->log($user, $redacted, $answer, [], false);

            return ['answer' => $answer, 'citations' => [], 'answered' => false];
        }

        $documents = array_map(fn ($h, $i) => ['name' => '['.($i + 1).'] '.($h['title']['ar'] ?? reset($h['title'])), 'text' => $h['content']], $relevant, array_keys($relevant));
        $run = $this->gateway->run($user, 'hub_answer', ['question' => $redacted], extraDocuments: $documents);
        $answer = $this->gateway->presentOutput($run, $user);

        preg_match_all('/\[(\d+)\]/u', $answer, $m);
        $used = array_unique(array_map('intval', $m[1]));
        $citations = collect($relevant)->map(fn ($h, $i) => ['n' => $i + 1, 'title' => $h['title'], 'kind' => $h['kind'], 'excerpt' => mb_substr($h['content'], 0, 280)])
            ->filter(fn ($c) => in_array($c['n'], $used, true))->values()->all();

        $answered = $citations !== [];
        $this->log($user, $redacted, $answer, $citations, $answered);

        return ['answer' => $answer, 'citations' => $citations, 'answered' => $answered];
    }

    private function log(User $user, string $question, string $answer, array $citations, bool $answered): void
    {
        HubQuestion::create(['user_id' => $user->id, 'question' => $question, 'answer' => $answer, 'citations' => $citations, 'answered' => $answered, 'created_at' => now()]);
    }
}
