<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_frameworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = BAREE global framework
            $table->string('key', 64);
            $table->json('name');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('draft'); // draft, published, archived
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'key', 'version']);
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('framework_id')->constrained('competency_frameworks')->cascadeOnDelete();
            $table->string('key', 64);
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('weight', 6, 2)->default(10);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['framework_id', 'key']);
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('framework_id')->constrained('competency_frameworks')->cascadeOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->string('key', 32);
            $table->json('name');
            $table->decimal('min_score', 5, 2);
            $table->string('color', 9)->nullable();
            // {"min_competency":{"responsible_ai":70},"no_competency_below":50,"requires_evidence":true}
            $table->json('requirements')->nullable();
            $table->timestamps();
            $table->unique(['framework_id', 'rank']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // mcq, scenario, practical
            $table->unsignedTinyInteger('difficulty')->default(2); // 1..3
            $table->decimal('weight', 5, 2)->default(1);
            $table->json('stem');
            $table->json('scenario')->nullable();
            $table->json('explanation')->nullable();
            $table->json('audience')->nullable(); // role template keys; null = everyone
            $table->string('status', 16)->default('published');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->json('label');
            $table->unsignedTinyInteger('score'); // 0..3, never sent to the browser
            $table->json('feedback')->nullable();
        });

        Schema::create('assessment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->constrained('competency_frameworks');
            $table->string('key', 64);
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('target_roles')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->json('settings')->nullable(); // {"confidence":true,"shuffle_options":true}
            $table->string('status', 16)->default('published');
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        // Items are grouped into sections ("categories") that drive the card-based UI.
        Schema::create('assessment_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('assessment_templates')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('section', 64);
            $table->unsignedSmallInteger('position');
            $table->unique(['template_id', 'question_id']);
        });

        Schema::create('assessment_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('assessment_templates');
            $table->string('name');
            $table->json('audience'); // {"departments":[1,2],"segments":[3],"roles":["teacher"]}
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 16)->default('scheduled');
            $table->timestamps();
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('assessment_templates');
            $table->foreignId('campaign_id')->nullable()->constrained('assessment_campaigns')->nullOnDelete();
            $table->unsignedInteger('framework_version');
            $table->string('status', 16)->default('in_progress'); // in_progress, scored, abandoned
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->foreignId('level_id')->nullable()->constrained('levels');
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->foreignId('option_id')->nullable()->constrained('question_options');
            $table->text('response')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable(); // 1..4
            $table->decimal('score', 5, 2)->default(0);
            $table->decimal('max_score', 5, 2)->default(3);
            $table->unsignedInteger('time_ms')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });

        // Evidence feeding the AI Passport: assessments, lab missions, studio work.
        Schema::create('competency_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->string('source', 16); // assessment, lab, studio, manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('recorded_at');
            $table->index(['tenant_id', 'user_id', 'competency_id']);
        });

        Schema::create('passports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('readiness', 5, 2)->default(0);
            $table->foreignId('level_id')->nullable()->constrained('levels');
            $table->json('competencies')->nullable(); // {"prompting":76,...}
            $table->json('next_level')->nullable();   // {"level_id":..,"gap":6,"blockers":[...]}
            $table->unsignedInteger('xp')->default(0);
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['passports', 'competency_scores', 'attempt_answers', 'assessment_attempts', 'assessment_campaigns',
            'assessment_template_items', 'assessment_templates', 'question_options', 'questions', 'levels',
            'competencies', 'competency_frameworks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
