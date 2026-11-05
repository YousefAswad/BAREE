<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Badge;
use App\Models\Course;
use App\Models\KnowledgeDocument;
use App\Models\LearningPath;
use App\Models\Mission;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Database\Seeder;
use Throwable;

final class LearningSeeder extends Seeder
{
    public function run(KnowledgeIndexer $indexer): void
    {
        $this->agents();

        foreach ([
            ['first_mission', 'أول مهمة', ['type' => 'missions_completed', 'count' => 1], '#8391DE'],
            ['data_analyst', 'محلّل البيانات', ['type' => 'path_completed', 'path' => 'data_analysis'], '#EE8B6B'],
            ['verifier', 'المتحقّق', ['type' => 'competency', 'key' => 'verification', 'min' => 75], '#44529E'],
            ['ai_foundations', 'أساسيات الذكاء الاصطناعي', ['type' => 'course_completed', 'course' => 'ai_for_teachers'], '#FFB48A'],
        ] as [$key, $name, $criteria, $color]) {
            Badge::updateOrCreate(['key' => $key], ['name' => ['ar' => $name], 'criteria' => $criteria, 'color' => $color]);
        }

        $coach = Agent::query()->whereNull('tenant_id')->where('key', 'lab_coach')->value('id');
        $data = LearningPath::updateOrCreate(['tenant_id' => null, 'key' => 'data_analysis'], ['title' => ['ar' => 'تحليل بيانات الطلاب'],
            'description' => ['ar' => 'من جدول علامات لقرار تعليمي، مع التحقّق من كل رقم.'], 'role_templates' => ['teacher', 'department_head', 'curriculum'], 'badge_key' => 'data_analyst']);
        $safety = LearningPath::updateOrCreate(['tenant_id' => null, 'key' => 'responsible_basics'], ['title' => ['ar' => 'أساسيات الاستخدام المسؤول'],
            'description' => ['ar' => 'كيف تستخدم الذكاء الاصطناعي بدون ما تعرّض بيانات أي حدا.'], 'role_templates' => null]);

        $rows = [];
        $marks = [[3, 2, 3, 0, 2], [3, 3, 2, 1, 3], [2, 2, 3, 0, 2], [3, 3, 3, 1, 3], [1, 2, 2, 0, 1], [3, 2, 3, 1, 2], [2, 3, 2, 0, 3], [3, 3, 3, 2, 3], [2, 1, 2, 0, 2], [3, 2, 3, 1, 2]];
        foreach ($marks as $i => $m) {
            $rows[] = ['الطالب' => 'طالب '.($i + 1), 'س1' => $m[0], 'س2' => $m[1], 'س3' => $m[2], 'س4' => $m[3], 'س5' => $m[4]];
        }

        Mission::updateOrCreate(['tenant_id' => null, 'key' => 'exam_error_hunt'], [
            'path_id' => $data->id, 'position' => 1, 'agent_id' => $coach, 'difficulty' => 2, 'xp' => 120, 'minutes' => 20, 'status' => 'published',
            'title' => ['ar' => 'حلّل نتائج امتحان واكتشف أكثر خطأ'],
            'description' => ['ar' => 'بيانات تجريبية لـ ١٠ طلاب بأسماء رمزية. علامة كل سؤال من ٣.'],
            'steps' => [
                ['key' => 'question', 'title' => ['ar' => 'حدّد سؤالك'], 'instruction' => ['ar' => 'شو السؤال اللي بدك تجاوبه من هالبيانات؟ اكتبه بجملة وحدة واضحة.']],
                ['key' => 'prompt', 'title' => ['ar' => 'اكتب الطلب'], 'instruction' => ['ar' => 'اكتب الطلب اللي رح تعطيه لأداة الذكاء الاصطناعي لتحلّل الجدول. تأكد إنه ما فيه أي بيانات تعرّف بطالب.']],
                ['key' => 'verify', 'title' => ['ar' => 'تحقّق'], 'instruction' => ['ar' => 'الأداة ردّت: "السؤال ٣ هو الأصعب لأنه أقل متوسط". راجع الجدول بنفسك: هل هاد صحيح؟ وإذا لا، شو السؤال الأصعب فعلًا؟']],
                ['key' => 'improve', 'title' => ['ar' => 'حسّن'], 'instruction' => ['ar' => 'اقترح نشاط علاجي قصير للسؤال الأصعب، مناسب لحصة ١٥ دقيقة.']],
            ],
            'rubric' => [['criterion' => 'clarity', 'weight' => 25], ['criterion' => 'privacy', 'weight' => 25], ['criterion' => 'verification', 'weight' => 30], ['criterion' => 'improvement', 'weight' => 20]],
            'competency_impact' => ['data_analysis' => 4, 'verification' => 2],
            'dataset' => ['table' => $rows, 'answers' => ['verify' => ['س4', 'السؤال 4', 'السؤال الرابع', 'سؤال 4', 'Q4']]],
        ]);

        $privacy = Mission::updateOrCreate(['tenant_id' => null, 'key' => 'privacy_check'], [
            'path_id' => $safety->id, 'position' => 1, 'agent_id' => $coach, 'difficulty' => 1, 'xp' => 80, 'minutes' => 10, 'status' => 'published',
            'title' => ['ar' => 'جهّز البيانات قبل ما ترفعها'],
            'description' => ['ar' => 'جدول تجريبي فيه بيانات حساسة مزروعة. لاقيها قبل ما توصل لأي أداة.'],
            'steps' => [
                ['key' => 'spot', 'title' => ['ar' => 'لاقِ البيانات الحساسة'], 'instruction' => ['ar' => 'شو الأعمدة اللي لازم تنشال أو تتحوّل لرموز قبل ما ترفع هالجدول؟']],
                ['key' => 'rewrite', 'title' => ['ar' => 'أعد الصياغة'], 'instruction' => ['ar' => 'اكتب كيف رح تجهّز الجدول وشو رح تطلب من الأداة بعدين.']],
            ],
            'rubric' => [['criterion' => 'privacy', 'weight' => 60], ['criterion' => 'clarity', 'weight' => 40]],
            'competency_impact' => ['responsible_ai' => 4],
            'dataset' => ['table' => [['الاسم' => 'سلمى أ.', 'رقم الهوية' => '4xxxxxxx1', 'العلامة' => 18], ['الاسم' => 'يوسف ب.', 'رقم الهوية' => '4xxxxxxx7', 'العلامة' => 15]],
                'answers' => ['spot' => ['الاسم', 'الأسماء', 'رقم الهوية', 'الهوية']]],
        ]);

        $course = Course::updateOrCreate(['tenant_id' => null, 'key' => 'ai_for_teachers'], ['title' => ['ar' => 'AI للمعلّمين: الأساسيات'],
            'description' => ['ar' => 'مسار قصير بيخلط قراءة واختبار ومهمة عملية بالمختبر.'], 'role_templates' => ['teacher', 'department_head', 'curriculum'],
            'hours' => 2, 'certificate' => true, 'badge_key' => 'ai_foundations', 'status' => 'published']);
        $course->modules()->delete();
        $m1 = $course->modules()->create(['position' => 1, 'title' => ['ar' => 'أساسيات الذكاء الاصطناعي']]);
        $m1->lessons()->create(['position' => 1, 'type' => 'reading', 'title' => ['ar' => 'كيف بتشتغل أدوات الذكاء الاصطناعي'], 'minutes' => 6,
            'body' => "## الفكرة الأساسية\n\nأدوات مثل Claude بتولّد نص متوقع بناءً على أنماط تعلّمتها. هاد بيخليها ممتازة بالصياغة والتنظيم، وبتحتاج تحقّق بالأرقام والمصادر.\n\n## قاعدة ذهبية\n\n- أعطِ سياق واضح: الصف، المدة، الهدف.\n- راجع كل رقم وكل جواب قبل ما توزّع."]);
        $m1->lessons()->create(['position' => 2, 'type' => 'quiz', 'title' => ['ar' => 'تحقّق من فهمك'], 'minutes' => 4, 'quiz' => [
            ['q' => 'وين الذكاء الاصطناعي بيغلط أكثر؟', 'options' => ['بالصياغة', 'بالأرقام والمصادر', 'بالتلخيص'], 'answer' => 1],
            ['q' => 'شو أهم إشي بالطلب؟', 'options' => ['كلمة من فضلك', 'السياق والهدف', 'طول الطلب'], 'answer' => 1],
            ['q' => 'قبل توزيع ورقة عمل مولّدة:', 'options' => ['أوزّعها مباشرة', 'أحلّ الأسئلة وأتحقّق', 'أطلب ورقة ثانية'], 'answer' => 1],
        ]]);
        $m2 = $course->modules()->create(['position' => 2, 'title' => ['ar' => 'الاستخدام المسؤول']]);
        $m2->lessons()->create(['position' => 1, 'type' => 'lab', 'title' => ['ar' => 'مهمة: جهّز البيانات قبل ما ترفعها'], 'minutes' => 10, 'mission_id' => $privacy->id]);

        $docs = [
            ['guide', 'دليل التحقّق من المخرجات', "## قبل ما توزّع أي محتوى مولّد\n\n1. حلّ كل سؤال بنفسك.\n2. راجع كل رقم وتاريخ ومعادلة.\n3. تأكد إنه المصادر حقيقية وافتحها.\n4. شيل أي معلومة غير مؤكدة أو علّمها.\n5. تأكد إنه الأنشطة مناسبة لعمر الطلاب.\n6. اقرأ النص بصوت عالي: هل هو مفهوم؟\n7. اكتب إنه المحتوى مُنشأ بمساعدة الذكاء الاصطناعي."],
            ['prompt_template', 'قالب: بطاقة خروج من سؤالين', "## الطلب\n\nجهّز بطاقة خروج من سؤالين لدرس «الموضوع» للصف «الصف»: سؤال يقيس الفهم الأساسي وسؤال تطبيقي. أضف مفتاح إجابة مختصر."],
            ['research', 'أثر التغذية الراجعة الفورية', "## الخلاصة\n\nالتغذية الراجعة المحددة والسريعة بترفع التعلّم أكثر من العلامة وحدها، خاصة لما تركّز على الخطوة الجاية للطالب."],
        ];
        // Index now only when embeddings are configured; otherwise leave drafts to index later (no network calls in seeders).
        $canEmbed = config('knowledge.embeddings.driver') === 'fake'
            || (config('ai_gateway.providers.openai.key') && config('knowledge.embeddings.model'));

        foreach ($docs as [$kind, $title, $body]) {
            $document = KnowledgeDocument::query()->whereNull('tenant_id')->where('kind', $kind)->where('title->ar', $title)->first()
                ?? KnowledgeDocument::create(['tenant_id' => null, 'kind' => $kind, 'title' => ['ar' => $title], 'body' => $body, 'status' => 'draft']);
            $document->update(['body' => $body]);

            if (! $canEmbed) {
                continue;
            }
            try {
                $indexer->index($document);
            } catch (Throwable $e) {
                $document->update(['status' => 'draft']);
                $this->command?->warn('Knowledge document not indexed yet: '.$title);
            }
        }
    }

    private function agents(): void
    {
        $make = function (string $key, string $prompt, array $fields) {
            $agent = Agent::updateOrCreate(['tenant_id' => null, 'key' => $key], ['name' => ['ar' => $key], 'category' => 'system', 'content_type' => 'text',
                'status' => 'published', 'availability' => 'available', 'role_templates' => null]);
            $version = $agent->versions()->updateOrCreate(['version' => 1], [
                'system_prompt' => $prompt, 'prompt_block_keys' => ['language.arabic_education', 'safety.student_privacy'],
                'input_schema' => ['fields' => $fields], 'routing' => ['providers' => ['anthropic', 'openai'], 'tier' => 'light', 'max_tokens' => 1200],
                'customization_policy' => ['tenant' => ['instructions' => false], 'user' => ['instructions' => false], 'task' => ['instructions' => false]],
                'credits_cost' => 1, 'status' => 'published', 'published_at' => now(),
            ]);
            $agent->update(['published_version_id' => $version->id]);
        };
        $text = fn ($key, $max = 2000, $required = true) => ['key' => $key, 'type' => 'text', 'required' => $required, 'label' => ['ar' => $key, 'en' => $key], 'max' => $max];

        $make('lab_coach', "You are a warm, precise practice coach in BAREE's AI Lab. Score the learner's submission for this step against each rubric criterion from 0 to 100, give short feedback in Arabic (max 80 words), and one hint that guides without giving the answer. If the objective check is \"failed\", do not reveal the correct answer and set ready to false. Reply with JSON only: {\"scores\":{\"<criterion>\":<0-100>},\"feedback\":\"...\",\"hint\":\"...\",\"ready\":true|false}.",
            [$text('mission', 300), $text('step', 1000), $text('criteria', 300), $text('submission', 6000), ['key' => 'check', 'type' => 'select', 'required' => true, 'label' => ['ar' => 'check'],
                'options' => [['value' => 'passed', 'label' => ['ar' => 'passed']], ['value' => 'failed', 'label' => ['ar' => 'failed']], ['value' => 'none', 'label' => ['ar' => 'none']]]]]);
        $make('hub_answer', 'Answer the question using only the numbered documents provided. Cite every claim with its number in square brackets, like [1]. If the documents do not contain the answer, say clearly that it is not in the approved documents and do not guess. Answer in the language of the question, in under 180 words.',
            [$text('question', 1000)]);
    }
}
