<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- Lab ----------
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('role_templates')->nullable();
            $table->string('badge_key', 64)->nullable();
            $table->string('status', 16)->default('published');
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->foreignId('path_id')->nullable()->constrained('learning_paths')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('description')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(2);
            $table->json('dataset')->nullable();   // practice data (synthetic, anonymised) incl. planted errors
            $table->string('status', 16)->default('published');
        });

        Schema::table('mission_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('step_index')->default(0);
            $table->json('transcript')->nullable(); // per step: submission (redacted), coach feedback, scores
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('started_at')->nullable();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('name');
            $table->json('criteria'); // {"type":"path_completed","path":"data_analysis"} | {"type":"competency","key":"verification","min":75}
            $table->string('color', 9)->default('#44529E');
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->unique(['user_id', 'badge_id']);
        });

        Schema::create('xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason', 32);
            $table->string('idempotency_key', 120)->unique(); // the same achievement never pays twice
            $table->timestamp('created_at');
        });

        // ---------- Academy ----------
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('role_templates')->nullable();
            $table->decimal('hours', 5, 2)->default(1);
            $table->boolean('certificate')->default(true);
            $table->string('badge_key', 64)->nullable();
            $table->string('status', 16)->default('published');
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->json('title');
        });

        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('course_modules')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 16); // video, reading, quiz, lab
            $table->json('title');
            $table->longText('body')->nullable();          // Markdown
            $table->string('video_url')->nullable();
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->json('quiz')->nullable();              // [{"q":"..","options":[".."],"answer":1}] — answers stay server-side
            $table->unsignedSmallInteger('minutes')->default(5);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('mandatory')->default(false);
            $table->timestamp('due_at')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->ulid('serial')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('holder_name');
            $table->decimal('hours', 5, 2);
            $table->timestamp('issued_at');
            $table->string('signature', 64); // HMAC over the certificate fields: detects tampering
            $table->timestamp('revoked_at')->nullable();
        });

        // ---------- Knowledge Hub ----------
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = BAREE global resource
            $table->string('kind', 24); // policy, guide, prompt_template, research, video
            $table->json('title');
            $table->longText('body')->nullable();
            $table->foreignId('source_file_id')->nullable()->constrained('knowledge_files')->nullOnDelete();
            $table->json('audience')->nullable(); // role templates; null = everyone
            $table->string('status', 16)->default('draft'); // draft, indexing, published, failed
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->text('normalized'); // Arabic-normalised text for keyword search
            $table->json('embedding')->nullable(); // portable copy; PostgreSQL also gets a pgvector column below
            $table->index(['tenant_id', 'document_id']);
        });

        Schema::create('hub_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('question');          // redacted
            $table->longText('answer')->nullable();
            $table->json('citations')->nullable();
            $table->boolean('answered')->default(false);
            $table->unsignedTinyInteger('helpful')->nullable();
            $table->timestamp('created_at');
        });

        // ---------- Feedback (feeds the Super Admin feedback centre) ----------
        Schema::create('output_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->json('reasons')->nullable();   // ["too_long","inaccurate","not_local","language"]
            $table->text('comment')->nullable();   // redacted
            $table->unsignedInteger('edit_distance')->nullable();
            $table->boolean('share_with_baree')->default(true); // respects the user's consent setting
            $table->timestamp('created_at');
            $table->index(['agent_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding_vec vector('.(int) config('knowledge.dimensions', 1536).')');
            DB::statement('CREATE INDEX knowledge_chunks_embedding_hnsw ON knowledge_chunks USING hnsw (embedding_vec vector_cosine_ops)');

            foreach (['user_badges', 'xp_events', 'enrollments', 'lesson_progress', 'certificates', 'hub_questions', 'output_feedback'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint) WITH CHECK (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)");
            }
            foreach (['knowledge_documents', 'knowledge_chunks', 'learning_paths', 'courses'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_or_global ON {$table} USING (current_setting('app.bypass_rls', true) = 'on' OR tenant_id IS NULL OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint) WITH CHECK (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)");
            }
        }
    }

    public function down(): void
    {
        foreach (['output_feedback', 'hub_questions', 'knowledge_chunks', 'knowledge_documents', 'certificates', 'lesson_progress', 'enrollments',
            'course_lessons', 'course_modules', 'courses', 'xp_events', 'user_badges', 'badges'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('mission_attempts', fn (Blueprint $t) => $t->dropColumn(['step_index', 'transcript', 'score', 'started_at']));
        Schema::table('missions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('path_id');
            $t->dropColumn(['position', 'description', 'difficulty', 'dataset', 'status']);
        });
        Schema::dropIfExists('learning_paths');
    }
};
