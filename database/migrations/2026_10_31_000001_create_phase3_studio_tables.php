<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_files', function (Blueprint $table) {
            $table->longText('extracted_text')->nullable();
            $table->unsignedInteger('text_chars')->default(0);
            $table->timestamp('scanned_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable();
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->string('content_type', 16)->default('text'); // text, data, slides, image, video, model3d, voice, interactive, comms
            $table->string('availability', 16)->default('available'); // available, soon
            $table->json('output_types')->nullable();     // ["docx","pptx","pdf"]
            $table->json('competency_tags')->nullable();  // drives recommendations from passport gaps
            $table->boolean('human_review')->default(false); // sensitive tools: AI assists, a person decides
        });

        Schema::table('agent_versions', function (Blueprint $table) {
            // Multi-step workflow: [{"key":"plan","title":{"ar":".."},"prompt":"..","output_type":"lesson_plan","routing":{...}}]
            $table->json('workflow')->nullable();
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_version_id')->constrained();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('queued'); // queued, running, partial, succeeded, failed
            $table->json('inputs');
            $table->text('task_instructions')->nullable();
            $table->json('file_ids')->nullable();
            $table->json('steps'); // [{"key":"plan","status":"succeeded","run_id":..,"output_id":..}]
            $table->timestamps();
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->foreignId('workflow_run_id')->nullable();
            $table->string('step_key', 48)->nullable();
        });

        Schema::table('outputs', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('media_jobs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('output_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);      // image, speech, video, model3d
            $table->string('provider', 32);
            $table->string('status', 16)->default('queued'); // queued, submitted, processing, succeeded, failed, blocked
            $table->string('external_id')->nullable();
            $table->json('request');         // prompt is stored redacted
            $table->string('disk', 16)->nullable();
            $table->string('path')->nullable();
            $table->string('mime', 64)->nullable();
            $table->unsignedInteger('credits')->default(0);
            $table->unsignedSmallInteger('polls')->default(0);
            $table->string('error')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_id')->constrained()->cascadeOnDelete();
            $table->string('format', 8); // docx, pptx, pdf, html, md
            $table->string('status', 16)->default('queued');
            $table->string('disk', 16)->nullable();
            $table->string('path')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['workflow_runs', 'media_jobs', 'exports', 'share_links', 'knowledge_files'] as $table) {
                if ($table === 'knowledge_files') {
                    continue; // knowledge_files has nullable tenant_id (BAREE agent files): handled by an OR-null policy below
                }
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint) WITH CHECK (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)");
            }
            DB::statement('ALTER TABLE knowledge_files ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE knowledge_files FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_or_global ON knowledge_files USING (current_setting('app.bypass_rls', true) = 'on' OR tenant_id IS NULL OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint) WITH CHECK (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)");
        }
    }

    public function down(): void
    {
        foreach (['share_links', 'exports', 'media_jobs', 'workflow_runs'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('outputs', fn (Blueprint $t) => $t->dropColumn(['created_by', 'metadata', 'archived_at']));
        Schema::table('ai_runs', fn (Blueprint $t) => $t->dropColumn(['workflow_run_id', 'step_key']));
        Schema::table('agent_versions', fn (Blueprint $t) => $t->dropColumn('workflow'));
        Schema::table('agents', fn (Blueprint $t) => $t->dropColumn(['content_type', 'availability', 'output_types', 'competency_tags', 'human_review']));
        Schema::table('knowledge_files', fn (Blueprint $t) => $t->dropColumn(['extracted_text', 'text_chars', 'scanned_at', 'rejection_reason', 'uploaded_by']));
    }
};
