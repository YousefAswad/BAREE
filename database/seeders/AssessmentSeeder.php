<?php

namespace Database\Seeders;

use App\Models\AssessmentTemplate;
use App\Models\CompetencyFramework;
use App\Models\Question;
use Illuminate\Database\Seeder;

/** Sample initial assessment for teachers: 12 role-relevant scenarios across all competencies. */
final class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $framework = CompetencyFramework::query()->where('key', 'baree_ai_readiness')->firstOrFail();
        $c = $framework->competencies()->pluck('id', 'key');

        // [section, competency, type, stem, scenario|null, [option => score...]]
        $bank = [
            ['basics', 'ai_literacy', 'mcq', 'أي جملة بتوصف أدوات مثل Claude أو ChatGPT بشكل أدق؟', null,
                ['بتبحث بالإنترنت وبتنقل الجواب الصحيح' => 0, 'بتفهم الكلام زي الإنسان تمامًا' => 1, 'بتولّد نص متوقع بناءً على أنماط تعلّمتها، وممكن تغلط' => 3, 'بتعطي نفس الجواب دايمًا لنفس السؤال' => 1]],
            ['basics', 'ai_literacy', 'mcq', 'متى غالبًا الذكاء الاصطناعي بيغلط أكثر؟', null,
                ['بالصياغة والتلخيص' => 0, 'بالأرقام الدقيقة والمعلومات الحديثة والمصادر' => 3, 'بالترجمة البسيطة' => 1, 'ما بيغلط إذا كان الطلب واضح' => 0]],
            ['basics', 'ai_interaction', 'scenario', 'الجواب الأول ما عجبك. شو بتعمل؟', ['ar' => 'طلبت أفكار لنشاط صفّي، وطلعت أفكار عامة ومش مناسبة لصفك.'],
                ['ببطّل وبعمل النشاط بنفسي' => 0, 'بعيد نفس الطلب بالضبط' => 1, 'بحكيله مين طلابي وشو المشكلة بالأفكار وبطلب تعديل محدد' => 3, 'بطلب "أفكار أحسن"' => 1]],
            ['prompting', 'prompting', 'mcq', 'أي طلب بيعطي أفضل نتيجة؟', null,
                ['«أسئلة كسور»' => 0, '«أسئلة عن الكسور لصف رابع»' => 1, '«١٠ أسئلة كسور لصف رابع مع الإجابات»' => 2, '«١٠ أسئلة اختيار من متعدد عن جمع الكسور لصف رابع، بأمثلة من حياة الطلاب، مع الإجابات»' => 3]],
            ['prompting', 'prompting', 'scenario', 'شو أهم إشي تضيفه للطلب؟', ['ar' => 'بدك خطة درس لصف فيه طلاب بمستويات متفاوتة.'],
                ['اسم المدرسة' => 0, 'مستوى الطلاب والمدة والهدف المطلوب' => 3, 'كلمة "من فضلك"' => 0, 'طول الخطة بالكلمات' => 1]],
            ['verification', 'verification', 'scenario', 'شو أنسب إشي تعمله؟', ['ar' => 'ورقة عمل من AI طلعت مرتبة، بس جواب سؤال ٤ بمفتاح الإجابات غلط.'],
                ['بصلّح السؤال ٤ وبوزّع' => 1, 'بصلّح السؤال ٤ وبحلّ باقي الأسئلة بنفسي قبل التوزيع' => 3, 'بطلب ورقة جديدة من الأول' => 1, 'ببطّل أستخدم الأداة' => 0]],
            ['verification', 'verification', 'scenario', 'كيف بتتعامل مع إحصائية بتقرير مولّد؟', ['ar' => 'التقرير فيه "٧٣٪ من الطلاب بيفضّلوا التعلّم بالفيديو" بدون مصدر.'],
                ['بستخدمها، شكلها منطقية' => 0, 'بحذفها أو بدوّر على مصدر موثوق قبل ما أستخدمها' => 3, 'بخلّيها وبكتب "حسب الدراسات"' => 0, 'بسأل الأداة إذا متأكدة' => 1]],
            ['safety', 'responsible_ai', 'scenario', 'شو بتعمل بالملف؟', ['ar' => 'بدك تحلّل علامات صفك بأداة ذكاء اصطناعي عامة على الإنترنت.'],
                ['برفعه زي ما هو' => 0, 'بسأل زميل' => 1, 'بشيل الأسماء قبل الرفع' => 2, 'بشيل كل البيانات المعرّفة وبستخدم أداة معتمدة من المدرسة' => 3]],
            ['safety', 'responsible_ai', 'mcq', 'طالب سلّم تقرير واضح إنه مولّد بالكامل. شو أفضل خطوة أولى؟', null,
                ['علامة صفر مباشرة' => 0, 'أتجاهل الموضوع' => 0, 'أحكي معه عن سياسة المدرسة وأطلب يشرحلي عمله' => 3, 'أفحصه بكاشف AI وأعتمد النتيجة' => 1]],
            ['creation', 'content_creation', 'mcq', 'بدك نشاط تفاعلي لدرس. شو بتطلب من الأداة أول؟', null,
                ['"اعمل نشاط حلو"' => 0, 'الهدف التعليمي، الصف، المدة، وطريقة تنفيذ النشاط' => 3, 'عدد الشرائح' => 1, 'ألوان النشاط' => 0]],
            ['data', 'data_analysis', 'scenario', 'شو أفضل طلب للأداة؟', ['ar' => 'عندك جدول علامات ٣٠ طالب لامتحان من ١٠ أسئلة.'],
                ['"حلّل الجدول"' => 1, '"مين أشطر طالب؟"' => 0, '"شو أكثر سؤالين غلطوا فيهم، وشو نوع الخطأ، واقترح نشاط علاجي"' => 3, '"ارسم رسم بياني"' => 1]],
            ['productivity', 'productivity', 'mcq', 'وين بيوفّر الذكاء الاصطناعي وقتك أكثر إشي بالأسبوع؟', null,
                ['ما بيوفّر' => 0, 'بالمهام المتكررة: أوراق عمل، رسائل، تقارير، مع مراجعتي' => 3, 'بتصحيح كل شي بدون مراجعة' => 0, 'بس بالترجمة' => 1]],
        ];

        $template = AssessmentTemplate::updateOrCreate(['tenant_id' => null, 'key' => 'teacher_initial'], [
            'framework_id' => $framework->id,
            'name' => ['ar' => 'تقييم الجاهزية الأولي للمعلّمين', 'en' => 'Initial readiness assessment for teachers'],
            'target_roles' => ['teacher'],
            'time_limit_minutes' => 20,
            'settings' => ['confidence' => true, 'shuffle_options' => true],
            'status' => 'published',
        ]);

        foreach ($bank as $i => [$section, $competency, $type, $stem, $scenario, $options]) {
            $question = Question::create([
                'tenant_id' => null, 'competency_id' => $c[$competency], 'type' => $type, 'difficulty' => 2, 'weight' => 1,
                'stem' => ['ar' => $stem], 'scenario' => $scenario, 'audience' => ['teacher'], 'status' => 'published',
            ]);
            $position = 0;
            foreach ($options as $label => $score) {
                $question->options()->create(['position' => $position++, 'label' => ['ar' => $label], 'score' => $score]);
            }
            $template->items()->updateOrCreate(['question_id' => $question->id], ['section' => $section, 'position' => $i]);
        }
    }
}
