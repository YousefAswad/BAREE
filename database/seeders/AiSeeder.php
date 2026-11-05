<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AiProvider;
use App\Models\PromptBlock;
use Illuminate\Database\Seeder;

final class AiSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['anthropic', 'anthropic', 'https://api.anthropic.com', true],
            ['openai', 'openai_compatible', 'https://api.openai.com/v1', true],
            ['openrouter', 'openai_compatible', 'https://openrouter.ai/api/v1', false],
            ['ollama', 'openai_compatible', 'http://localhost:11434/v1', false],
            ['fake', 'fake', null, app()->environment('local', 'testing')],
        ];
        foreach ($providers as [$key, $driver, $url, $enabled]) {
            AiProvider::updateOrCreate(['key' => $key], ['driver' => $driver, 'base_url' => $url, 'enabled' => $enabled,
                'data_policy' => ['redact_pii' => true]]);
        }

        $blocks = [
            ['quality.verification', 'quality', "Before answering, verify every number, equation, date and answer key. If you are not certain about a fact, mark it inline with «تحقّق» instead of stating it as fact."],
            ['language.arabic_education', 'style', 'Write in clear Modern Standard Arabic suitable for the stated grade, unless the user asks otherwise. Prefer local, familiar examples. Avoid unnecessary English terms.'],
            ['safety.student_privacy', 'safety', 'Never ask for or produce information that identifies a student. Tokens like [PERSON_1] are placeholders for real people: keep them exactly as written.'],
        ];
        foreach ($blocks as [$key, $group, $content]) {
            PromptBlock::updateOrCreate(['key' => $key, 'version' => 1], ['group' => $group, 'content' => $content, 'status' => 'published']);
        }

        $agent = Agent::updateOrCreate(['tenant_id' => null, 'key' => 'lesson_pack'], [
            'name' => ['ar' => 'وكيل حزمة الدرس', 'en' => 'Lesson pack agent'],
            'description' => ['ar' => 'خطة، عرض، نشاط، ورقة عمل واختبار مترابطين لنفس الدرس.'],
            'category' => 'teaching',
            'role_templates' => ['teacher', 'department_head', 'curriculum'],
            'status' => 'published',
        ]);

        $version = $agent->versions()->updateOrCreate(['version' => 1], [
            'system_prompt' => "You are BAREE's expert lesson designer for K-12 teachers. Build a complete, coherent lesson pack for the given grade, subject and topic: objectives, a timed lesson flow, an interactive activity, a worksheet differentiated into three levels, a short quiz with an answer key, and homework. Every activity must map to an objective. Total timings must equal the lesson duration exactly. Output clean Markdown with one H2 per component.",
            'prompt_block_keys' => ['quality.verification', 'language.arabic_education', 'safety.student_privacy'],
            'input_schema' => ['fields' => [
                ['key' => 'grade', 'type' => 'text', 'required' => true, 'label' => ['ar' => 'الصف', 'en' => 'Grade'], 'source' => 'profile'],
                ['key' => 'subject', 'type' => 'text', 'required' => true, 'label' => ['ar' => 'المادة', 'en' => 'Subject'], 'source' => 'profile'],
                ['key' => 'topic', 'type' => 'text', 'required' => true, 'max' => 300, 'label' => ['ar' => 'موضوع الدرس', 'en' => 'Topic']],
                ['key' => 'duration', 'type' => 'number', 'required' => true, 'label' => ['ar' => 'مدة الحصة بالدقائق', 'en' => 'Duration in minutes']],
                ['key' => 'objectives', 'type' => 'text', 'required' => false, 'label' => ['ar' => 'أهداف التعلّم', 'en' => 'Learning objectives']],
                ['key' => 'student_level', 'type' => 'select', 'required' => true, 'label' => ['ar' => 'مستوى الطلاب', 'en' => 'Student level'],
                    'options' => [['value' => 'similar', 'label' => ['ar' => 'متقارب']], ['value' => 'mixed', 'label' => ['ar' => 'متفاوت']], ['value' => 'advanced', 'label' => ['ar' => 'متقدّم']]]],
            ]],
            'routing' => ['providers' => ['anthropic', 'openai'], 'tier' => 'standard', 'max_tokens' => 6000],
            'customization_policy' => [
                'tenant' => ['instructions' => true, 'files' => true],
                'workspace' => ['instructions' => true],
                'user' => ['instructions' => true, 'files' => true, 'examples' => true],
                'task' => ['instructions' => true],
            ],
            'credits_cost' => 18,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $agent->update(['published_version_id' => $version->id]);
    }
}
