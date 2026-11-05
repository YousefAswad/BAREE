<?php

namespace Database\Seeders;

use App\Models\CompetencyFramework;
use Illuminate\Database\Seeder;

final class CompetencyFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        $framework = CompetencyFramework::updateOrCreate(
            ['tenant_id' => null, 'key' => 'baree_ai_readiness', 'version' => 1],
            ['name' => ['ar' => 'إطار BAREE للجاهزية', 'en' => 'BAREE AI Readiness'], 'status' => 'published', 'is_default' => true],
        );

        $competencies = [
            ['ai_literacy', 'فهم الذكاء الاصطناعي', 'AI literacy', 12],
            ['ai_interaction', 'التواصل مع AI', 'AI interaction', 10],
            ['prompting', 'كتابة الطلب وتوجيهه', 'Prompting', 14],
            ['verification', 'التحقّق والتفكير النقدي', 'Critical thinking and verification', 12],
            ['responsible_ai', 'الاستخدام المسؤول', 'Responsible AI', 12],
            ['content_creation', 'صناعة المحتوى', 'Content creation', 12],
            ['data_analysis', 'البيانات والتحليل', 'Data and analysis', 10],
            ['productivity', 'الإنتاجية اليومية', 'Workflow productivity', 10],
            ['role_specific', 'مهارات الدور', 'Role-specific capability', 8],
        ];
        foreach ($competencies as $i => [$key, $ar, $en, $weight]) {
            $framework->competencies()->updateOrCreate(['key' => $key], ['name' => ['ar' => $ar, 'en' => $en], 'weight' => $weight, 'position' => $i]);
        }

        $levels = [
            [1, 'explorer', 'مستكشف AI', 'AI Explorer', 0, '#CDC9D6', null],
            [2, 'learner', 'متعلّم AI', 'AI Learner', 30, '#C6CDF4', null],
            [3, 'practitioner', 'ممارس AI', 'AI Practitioner', 50, '#8391DE', null],
            [4, 'creator', 'صانع AI', 'AI Creator', 65, '#44529E', null],
            [5, 'innovator', 'مبتكر AI', 'AI Innovator', 80, '#EE8B6B', ['no_competency_below' => 50]],
            [6, 'champion', 'رائد AI', 'AI Champion', 90, '#FFB48A', ['no_competency_below' => 50, 'min_competency' => ['responsible_ai' => 70]]],
        ];
        foreach ($levels as [$rank, $key, $ar, $en, $min, $color, $requirements]) {
            $framework->levels()->updateOrCreate(['rank' => $rank], [
                'key' => $key, 'name' => ['ar' => $ar, 'en' => $en], 'min_score' => $min, 'color' => $color, 'requirements' => $requirements,
            ]);
        }
    }
}
