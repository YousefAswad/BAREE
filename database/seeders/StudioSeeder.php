<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentVersion;
use Illuminate\Database\Seeder;

/**
 * The tool catalogue for every department (94 tools), the lesson-pack workflow, and working versions
 * for the most used tools. Tools without a version appear as "قريبًا" until BAREE trains and tests them.
 */
final class StudioSeeder extends Seeder
{
    private const ROLES = [
        'all' => null, 'teach' => ['teacher', 'department_head', 'curriculum'], 'cur' => ['curriculum', 'department_head'],
        'lead' => ['leadership'], 'hr' => ['hr', 'leadership'], 'fin' => ['finance', 'leadership'], 'adm' => ['administration'],
        'stu' => ['student_affairs'], 'comm' => ['communications'], 'it' => ['it'], 'qa' => ['quality', 'leadership'],
        'train' => ['training'], 'admit' => ['admissions'],
    ];

    private const TAGS = [
        'text' => ['prompting', 'productivity'], 'data' => ['data_analysis', 'verification'], 'slides' => ['content_creation'],
        'image' => ['content_creation'], 'video' => ['content_creation'], 'model3d' => ['content_creation'], 'voice' => ['content_creation'],
        'interactive' => ['content_creation', 'role_specific'], 'comms' => ['ai_interaction', 'productivity'],
    ];

    private const FORMATS = [
        'text' => ['docx', 'pdf', 'md'], 'data' => ['docx', 'pdf'], 'slides' => ['pptx', 'pdf'], 'comms' => ['md'],
        'interactive' => ['html', 'pdf'], 'image' => [], 'video' => [], 'model3d' => [], 'voice' => [],
    ];

    public function run(): void
    {
        foreach ($this->catalogue() as [$key, $dept, $name, $description, $type, $flag]) {
            Agent::updateOrCreate(['tenant_id' => null, 'key' => $key], [
                'name' => ['ar' => $name], 'description' => ['ar' => $description], 'category' => $dept,
                'role_templates' => self::ROLES[$dept], 'content_type' => $type,
                'competency_tags' => array_values(array_unique(array_merge(self::TAGS[$type], $flag === 'human' ? ['responsible_ai', 'verification'] : []))),
                'output_types' => self::FORMATS[$type], 'human_review' => $flag === 'human',
                'availability' => 'soon', 'status' => 'catalog',
            ]);
        }

        $this->lessonPackWorkflow();

        // Working tools in phase 3. Each one: system prompt + guided form. Everything else stays "soon".
        $this->publish('worksheet', 'You create differentiated worksheets in three clearly labelled levels (support, core, challenge) with an answer key. Every item maps to the stated objective. Use the grade-appropriate Arabic register.',
            [$this->f('grade', 'الصف', source: 'profile'), $this->f('subject', 'المادة', source: 'profile'), $this->f('topic', 'الموضوع'), $this->f('items', 'عدد الأسئلة', 'number')], 6);
        $this->publish('quiz_bank', 'You write assessment items: multiple choice with plausible distractors, short answer, and one higher-order item. Include an answer key and the targeted skill for each item. Never reuse wording from copyrighted exams.',
            [$this->f('grade', 'الصف', source: 'profile'), $this->f('subject', 'المادة', source: 'profile'), $this->f('topic', 'الموضوع'), $this->f('count', 'عدد الأسئلة', 'number')], 4);
        $this->publish('parent_message', 'You draft clear, respectful messages from a school to parents. Never compare students, never include grades of other students, keep it under 180 words, end with a concrete next step and a contact line placeholder.',
            [$this->f('purpose', 'غرض الرسالة'), $this->f('tone', 'النبرة', 'select', options: ['warm' => 'ودّية', 'formal' => 'رسمية']), $this->f('details', 'تفاصيل مهمة', required: false)], 1);
        $this->publish('meeting_minutes', 'You turn meeting notes or transcripts into minutes: attendees (as given), decisions, action items with owner and due date, open questions. Do not invent decisions that are not in the notes.',
            [$this->f('notes', 'الملاحظات أو النص', max: 20000), $this->f('meeting', 'اسم الاجتماع', required: false)], 2);
        $this->publish('official_letter', 'You write formal Arabic letters on behalf of an institution: subject line, greeting, body, closing, signature block placeholders. Follow the institution rules and template if provided.',
            [$this->f('recipient', 'الجهة المرسل إليها'), $this->f('subject', 'الموضوع'), $this->f('points', 'النقاط الأساسية', max: 4000)], 2);
        $this->publish('survey_analyzer', 'You analyse survey data supplied as a document. Report participation, results per dimension, the strongest and weakest areas, and themes from open comments with counts. Never report any group smaller than 5 people. Cite the question numbers you used.',
            [$this->f('question', 'شو بدك تعرف من الاستبيان؟')], 6, routingTier: 'advanced');
        $this->publish('job_description', 'You write job descriptions for educational institutions: purpose, key responsibilities, qualifications, competencies, KPIs. Use inclusive, non-discriminatory language; never add age, gender or nationality requirements.',
            [$this->f('title', 'المسمّى'), $this->f('department', 'القسم'), $this->f('level', 'المستوى', 'select', options: ['junior' => 'مبتدئ', 'mid' => 'متوسط', 'senior' => 'خبير'])], 3);

        // Internal helpers used by the studio (not listed in the catalogue).
        $this->publish('scenario_scene', 'You write one scene of a learning scenario. For "text": a short narrative or explanation (max 150 words). For "quiz": 3 multiple-choice questions with answers. For "activity": a group activity with roles, steps, timing and materials. Keep the scene consistent with the topic and grade.',
            [$this->f('scene_type', 'نوع المشهد', 'select', options: ['text' => 'نص', 'quiz' => 'سؤال', 'activity' => 'نشاط']), $this->f('grade', 'الصف', required: false),
                $this->f('subject', 'المادة', required: false), $this->f('topic', 'الموضوع'), $this->f('request', 'المطلوب', max: 2000)], 2, category: 'system');
        $this->publish('refine_output', 'You revise the given document according to the instruction only. Keep everything else as it is, keep Markdown structure, and keep placeholders like [PERSON_1] unchanged.',
            [$this->f('instruction', 'التعديل المطلوب', max: 1000), $this->f('document', 'المستند', max: 60000)], 2, category: 'system');
    }

    private function lessonPackWorkflow(): void
    {
        $agent = Agent::query()->whereNull('tenant_id')->where('key', 'lesson_pack')->firstOrFail();
        $agent->update(['content_type' => 'slides', 'availability' => 'available', 'status' => 'published', 'category' => 'teach',
            'output_types' => ['docx', 'pptx', 'pdf'], 'competency_tags' => ['content_creation', 'prompting']]);

        $step = fn ($key, $ar, $type, $credits, $prompt) => ['key' => $key, 'title' => ['ar' => $ar], 'output_type' => $type, 'credits' => $credits, 'prompt' => $prompt];
        AgentVersion::query()->whereKey($agent->published_version_id)->update(['workflow' => json_encode([
            $step('plan', 'خطة الدرس', 'lesson_plan', 4, 'Write only the lesson plan: objectives, prior knowledge, a timed flow whose minutes add up exactly to the duration, differentiation, and assessment.'),
            $step('slides', 'العرض التقديمي', 'slides', 4, 'Write only the slide deck as Markdown: one "## " heading per slide, at most 5 short bullets each, speaker notes in a final "Notes" bullet. Follow the plan exactly.'),
            $step('activity', 'النشاط التفاعلي', 'activity', 2, 'Write only one interactive classroom activity that serves the main objective: setup, steps, timing, grouping, and what success looks like.'),
            $step('worksheet', 'ورقة العمل', 'worksheet', 3, 'Write only the worksheet in three levels (support, core, challenge) with an answer key, aligned to the plan.'),
            $step('quiz', 'الاختبار القصير', 'quiz', 3, 'Write only a 5-question exit quiz with an answer key and the objective each question checks.'),
            $step('homework', 'الواجب البيتي', 'homework', 2, 'Write only a short homework task (15 minutes) that practises the lesson and can be done without devices.'),
        ], JSON_UNESCAPED_UNICODE)]);
    }

    private function publish(string $key, string $prompt, array $fields, int $credits, string $routingTier = 'standard', ?string $category = null): void
    {
        $agent = Agent::query()->whereNull('tenant_id')->where('key', $key)->first()
            ?? Agent::create(['tenant_id' => null, 'key' => $key, 'name' => ['ar' => $key], 'category' => $category ?? 'system', 'content_type' => 'text', 'status' => 'draft']);

        $version = $agent->versions()->updateOrCreate(['version' => 1], [
            'system_prompt' => $prompt,
            'prompt_block_keys' => ['quality.verification', 'language.arabic_education', 'safety.student_privacy'],
            'input_schema' => ['fields' => $fields],
            'routing' => ['providers' => ['anthropic', 'openai'], 'tier' => $routingTier, 'max_tokens' => 4000],
            'customization_policy' => ['tenant' => ['instructions' => true, 'files' => true], 'workspace' => ['instructions' => true], 'user' => ['instructions' => true], 'task' => ['instructions' => true]],
            'credits_cost' => $credits, 'status' => 'published', 'published_at' => now(),
        ]);

        $agent->update(['published_version_id' => $version->id, 'status' => 'published', 'availability' => 'available'] + ($category ? ['category' => $category] : []));
    }

    private function f(string $key, string $label, string $type = 'text', bool $required = true, ?string $source = null, int $max = 2000, array $options = []): array
    {
        return array_filter([
            'key' => $key, 'type' => $type, 'required' => $required, 'label' => ['ar' => $label, 'en' => $key], 'source' => $source,
            'max' => $type === 'text' ? $max : null,
            'options' => $options ? array_map(fn ($v, $l) => ['value' => $v, 'label' => ['ar' => $l]], array_keys($options), $options) : null,
        ], fn ($v) => $v !== null);
    }

    /** [key, department, name, description, content type, flag] */
    private function catalogue(): array
    {
        return [
            ['email_assistant', 'all', 'مساعد البريد والردود', 'يكتب ويرد على الرسائل بنبرة مناسبة', 'comms', null],
            ['meeting_minutes', 'all', 'ملخّص الاجتماعات ومحاضرها', 'من ملاحظات أو تسجيل لمحضر بقرارات ومهام', 'text', 'new'],
            ['doc_translator', 'all', 'مترجم المستندات', 'ترجمة عربي وإنجليزي مع الحفاظ على التنسيق', 'text', null],
            ['doc_analyzer', 'all', 'محلّل المستندات', 'لخّص، استخرج، أو اسأل أي ملف', 'text', null],
            ['sheet_analyzer', 'all', 'محلّل الجداول', 'ارفع جدول واستلم تحليل ورسوم', 'data', null],
            ['deck_builder', 'all', 'بنّاء العروض التقديمية', 'من فكرة أو ملف لعرض جاهز', 'slides', null],
            ['report_builder', 'all', 'بنّاء التقارير', 'تقرير منظّم من بيانات وملاحظات', 'text', null],
            ['image_maker', 'all', 'مولّد الصور والرسوم', 'رسومات وإنفوجرافيك لأي غرض', 'image', null],
            ['voice_over', 'all', 'التعليق الصوتي', 'صوت عربي طبيعي لأي نص', 'voice', null],
            ['planner', 'all', 'منظّم الخطط والمهام', 'حوّل هدف لخطة بخطوات ومواعيد', 'text', null],
            ['research_assistant', 'all', 'مساعد البحث', 'مصادر موثوقة وملخّص بالعربي', 'text', null],
            ['lesson_pack', 'teach', 'حزمة الدرس الكاملة', 'خطة، عرض، نشاط، ورقة عمل واختبار مترابطين', 'slides', null],
            ['lesson_plan', 'teach', 'خطة درس', 'أهداف وأنشطة وتقويم بالدقيقة', 'text', null],
            ['worksheet', 'teach', 'ورقة عمل متمايزة', 'ثلاث مستويات لنفس الدرس', 'text', null],
            ['quiz_bank', 'teach', 'بنك أسئلة واختبارات', 'أسئلة بمستويات مع مفتاح إجابات', 'interactive', null],
            ['rubric', 'teach', 'روبرك تقييم', 'معايير واضحة بدرجات', 'text', null],
            ['student_feedback', 'teach', 'تغذية راجعة للطلاب', 'ملاحظات شخصية بدون أسماء', 'text', null],
            ['class_activity', 'teach', 'نشاط تفاعلي', 'نشاط صفّي برابط لأي جهاز', 'interactive', null],
            ['edu_game', 'teach', 'لعبة تعليمية', 'تحدّيات ونقاط للصف', 'interactive', 'new'],
            ['scenario', 'teach', 'موقف تعليمي بوسائط', 'مشاهد فيديو و3D وصور بقصة وحدة', 'interactive', null],
            ['model_3d', 'teach', 'نموذج ثلاثي الأبعاد', 'مجسّم بيلفّه الطالب ويفكّه', 'model3d', null],
            ['edu_video', 'teach', 'فيديو تعليمي قصير', 'رسوم متحركة أو شرح بصوت', 'video', null],
            ['avatar_presenter', 'teach', 'مقدّم افتراضي', 'أفاتار بيشرح الدرس', 'video', 'soon'],
            ['flashcards', 'teach', 'بطاقات مراجعة', 'بطاقات ذاكرة للطباعة أو الشاشة', 'interactive', null],
            ['remedial_plan', 'teach', 'خطة علاجية لطالب', 'أنشطة يومية ومتابعة مع الأهل', 'text', null],
            ['inclusion_adapter', 'teach', 'تكييف للدمج', 'نسخة مكيّفة للطلاب ذوي الاحتياجات', 'text', null],
            ['parent_message', 'teach', 'رسالة للأهالي', 'واضحة ومحترمة وجاهزة للإرسال', 'comms', null],
            ['curriculum_alignment', 'cur', 'مواءمة المنهاج', 'قارن الخطط بأهداف المنهاج المعتمد', 'data', null],
            ['term_plan', 'cur', 'الخطة الفصلية', 'توزيع الوحدات على الأسابيع', 'text', null],
            ['dept_question_bank', 'cur', 'بنك أسئلة موحّد للقسم', 'أسئلة مشتركة بمعايير موحّدة', 'interactive', null],
            ['dept_results', 'cur', 'تحليل نتائج القسم', 'فجوات بين الشعب', 'data', null],
            ['exam_quality', 'cur', 'مراجعة جودة الامتحانات', 'صعوبة وتمييز ومواءمة الأسئلة', 'data', 'new'],
            ['skills_map', 'cur', 'خريطة المهارات للمادة', 'تسلسل المهارات عبر الصفوف', 'slides', null],
            ['strategic_plan', 'lead', 'مخطّط الخطة الاستراتيجية', 'أهداف ومؤشرات ومبادرات', 'text', null],
            ['school_improvement', 'lead', 'خطة تطوير المدرسة', 'من البيانات لأولويات وخطة عمل', 'text', null],
            ['decision_dashboard', 'lead', 'لوحة مؤشرات للقرار', 'أهم الأرقام بصفحة وحدة', 'data', null],
            ['board_report', 'lead', 'تقرير مجلس الإدارة', 'تقرير وعرض بنفس الوقت', 'slides', null],
            ['community_messages', 'lead', 'رسائل للمجتمع المدرسي', 'للأهالي والموظفين والشركاء', 'comms', null],
            ['meeting_prep', 'lead', 'جدول أعمال وتحضير اجتماع', 'نقاط ووثائق وأسئلة متوقعة', 'text', null],
            ['policy_analyzer', 'lead', 'محلّل السياسات', 'قارن سياساتك بالمعايير', 'text', null],
            ['job_description', 'hr', 'بنّاء الوصف الوظيفي', 'مهام ومؤهلات ومؤشرات أداء', 'text', null],
            ['cv_screening', 'hr', 'فرز السير الذاتية', 'مطابقة مع الوصف الوظيفي', 'data', 'human'],
            ['interview_questions', 'hr', 'أسئلة المقابلات', 'أسئلة سلوكية مع معايير تقييم', 'text', null],
            ['performance_review', 'hr', 'مساعد تقييم الأداء', 'ملخّص عادل مبني على أدلة', 'text', 'human'],
            ['team_training_plan', 'hr', 'خطة تدريب للفريق', 'حسب فجوات الكفاءات', 'text', null],
            ['survey_analyzer', 'hr', 'محلّل الاستبيانات', 'نتائج وتعليقات مصنّفة', 'data', null],
            ['policy_builder', 'hr', 'بنّاء السياسات الداخلية', 'صياغة واضحة ومتّسقة', 'text', null],
            ['onboarding_kit', 'hr', 'حقيبة تهيئة موظف جديد', 'دليل وجدول أول أسبوع ورسالة ترحيب', 'slides', 'new'],
            ['budget_analyzer', 'fin', 'محلّل الميزانية', 'قارن المخطط بالفعلي', 'data', null],
            ['monthly_finance', 'fin', 'التقرير المالي الشهري', 'أرقام ورسوم وتعليق مختصر', 'data', null],
            ['cash_forecast', 'fin', 'توقّع التدفّق النقدي', 'سيناريوهات للأشهر الجاية', 'data', null],
            ['invoice_review', 'fin', 'مراجعة الفواتير والمصاريف', 'تصنيف وتنبيه على غير المعتاد', 'data', 'human'],
            ['finance_explainer', 'fin', 'شرح الأرقام لغير الماليين', 'ملخّص مالي بلغة بسيطة', 'text', null],
            ['vendor_comparison', 'fin', 'مقارنة عروض الموردين', 'جدول مقارنة وتوصية', 'data', null],
            ['fee_reminders', 'fin', 'تذكيرات الرسوم للأهالي', 'رسائل لطيفة ومحترمة', 'comms', null],
            ['official_letter', 'adm', 'كاتب الكتب الرسمية', 'على نموذج المؤسسة وبأسلوبها', 'text', null],
            ['minutes_admin', 'adm', 'محاضر الاجتماعات', 'قرارات ومسؤوليات ومواعيد', 'text', null],
            ['scheduler', 'adm', 'الجداول والمواعيد', 'تنظيم حصص وقاعات واجتماعات', 'data', null],
            ['archiver', 'adm', 'تصنيف وأرشفة المستندات', 'تسمية وتصنيف تلقائي', 'data', null],
            ['forms', 'adm', 'النماذج والطلبات', 'نماذج جاهزة للتعبئة', 'text', null],
            ['faq_replies', 'adm', 'ردود الاستفسارات المتكررة', 'إجابات جاهزة ومحدّثة', 'comms', null],
            ['support_plan', 'stu', 'خطة دعم فردية', 'أهداف وتدخلات ومتابعة', 'text', 'human'],
            ['attendance_analysis', 'stu', 'تحليل الحضور والغياب', 'أنماط وتنبيهات مبكرة', 'data', null],
            ['sensitive_messages', 'stu', 'رسائل حساسة للأهالي', 'صياغة متأنية لمواقف صعبة', 'comms', 'human'],
            ['counselling_resources', 'stu', 'موارد إرشادية', 'أنشطة ومواد حسب الموضوع والعمر', 'text', null],
            ['activities_plan', 'stu', 'خطة الأنشطة الطلابية', 'فعاليات بأهداف وميزانية', 'text', null],
            ['behaviour_report', 'stu', 'تقرير السلوك المجمّع', 'اتجاهات بدون أسماء', 'data', null],
            ['social_posts', 'comm', 'منشورات التواصل الاجتماعي', 'نصوص وأفكار صور لأسبوع', 'comms', null],
            ['newsletter', 'comm', 'النشرة الإخبارية', 'أخبار المؤسسة بتنسيق جذّاب', 'slides', null],
            ['campaign_images', 'comm', 'صور الحملات', 'صور بهوية المؤسسة', 'image', null],
            ['promo_video', 'comm', 'فيديو ترويجي قصير', 'من نص لفيديو ٣٠ ثانية', 'video', null],
            ['event_page', 'comm', 'صفحة فعالية', 'تفاصيل وتسجيل وتذكير', 'interactive', null],
            ['annual_report', 'comm', 'التقرير السنوي المصمّم', 'قصة السنة بالأرقام والصور', 'slides', null],
            ['success_stories', 'comm', 'قصص النجاح', 'من ملاحظات لقصة ملهمة', 'text', null],
            ['user_guides', 'it', 'دليل استخدام خطوة بخطوة', 'أدلة مصوّرة لأي نظام', 'slides', null],
            ['helpdesk', 'it', 'مساعد الدعم الفني', 'ردود على التذاكر الشائعة', 'comms', null],
            ['security_policy', 'it', 'سياسة أمن المعلومات', 'صياغة ومراجعة', 'text', null],
            ['asset_inventory', 'it', 'جرد الأجهزة والتخطيط', 'تحليل المخزون واحتياج الاستبدال', 'data', null],
            ['systems_explainer', 'it', 'شرح الأنظمة للموظفين', 'فيديو قصير أو بطاقات', 'video', null],
            ['accreditation_match', 'qa', 'مطابقة معايير الاعتماد', 'وين أنت من كل معيار', 'data', null],
            ['self_evaluation', 'qa', 'تقرير التقييم الذاتي', 'منظّم حسب المعايير', 'text', null],
            ['evidence_organizer', 'qa', 'تنظيم الأدلة والشواهد', 'ربط كل دليل بمعيار', 'data', null],
            ['improvement_plan', 'qa', 'خطة التحسين', 'أولويات ومسؤوليات ومؤشرات', 'text', null],
            ['satisfaction_surveys', 'qa', 'استبيانات الرضا', 'تصميم وتحليل', 'data', null],
            ['workshop_builder', 'train', 'بنّاء ورشة تدريبية', 'أهداف وأنشطة ومواد', 'slides', null],
            ['training_materials', 'train', 'مواد تدريب تفاعلية', 'تمارين وأسئلة للمتدربين', 'interactive', null],
            ['pre_post_test', 'train', 'اختبار قبلي وبعدي', 'قياس أثر التدريب', 'interactive', null],
            ['training_needs', 'train', 'تحليل احتياجات التدريب', 'من تقييمات الموظفين', 'data', null],
            ['certificates', 'train', 'شهادات الحضور', 'بتصميم المؤسسة', 'image', null],
            ['admission_replies', 'admit', 'ردود استفسارات القبول', 'إجابات من سياسات المؤسسة', 'comms', null],
            ['admission_letters', 'admit', 'رسائل القبول', 'رسائل شخصية جاهزة', 'comms', null],
            ['applications_analysis', 'admit', 'تحليل طلبات الالتحاق', 'أعداد وتوزيع وتوقّعات', 'data', null],
            ['virtual_tour', 'admit', 'جولة افتراضية في المدرسة', 'جولة ثلاثية الأبعاد للمرافق', 'model3d', 'soon'],
            ['new_student_guide', 'admit', 'دليل الطالب الجديد', 'كل اللي بيحتاجه بأول أسبوع', 'slides', null],
        ];
    }
}
