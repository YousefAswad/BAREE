<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_key', 64)->default('staff');
            $table->string('token_hash', 64)->unique(); // sha256 of the raw token; raw token only lives in the e-mail
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('import_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'email']);
        });

        Schema::create('user_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('path');
            $table->string('status', 16)->default('validating'); // validating, ready, importing, done, failed
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('valid')->default(0);
            $table->unsignedInteger('invited')->default(0);
            $table->json('report')->nullable(); // per-row problems: duplicate e-mail, unknown department...
            $table->timestamps();
        });

        // Names that must never reach a model provider (e.g. student roster). Encrypted at rest.
        Schema::create('protected_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->text('term');                // encrypted cast
            $table->string('term_hash', 64);     // HMAC for de-duplication without decrypting
            $table->string('source', 16)->default('manual'); // manual, roster_upload
            $table->timestamps();
            $table->unique(['tenant_id', 'term_hash']);
        });

        Schema::create('support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users');
            $table->string('reason', 500);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 32)->default('quality'); // quality, safety, privacy, performance
            $table->json('inputs');
            $table->text('task_instructions')->nullable();
            // [{"type":"contains","value":"..."},{"type":"not_contains","value":"..."},{"type":"regex","value":"/.../u"},{"type":"max_chars","value":8000}]
            $table->json('assertions');
            $table->boolean('blocking')->default(true); // a failing blocking case prevents publishing
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_test_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_id')->constrained('agent_test_cases')->cascadeOnDelete();
            $table->string('batch', 26);
            $table->string('status', 16); // passed, failed, error
            $table->longText('output')->nullable();
            $table->json('checks')->nullable();
            $table->string('provider', 32)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at');
            $table->index(['agent_version_id', 'batch']);
        });

        Schema::table('agent_versions', function (Blueprint $table) {
            // {"mode":"pilot","tenant_ids":[3,7]} | {"mode":"percent","percent":25} | {"mode":"all"}
            $table->json('rollout')->nullable();
            $table->text('release_notes')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['invitations', 'user_imports', 'protected_terms'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint) WITH CHECK (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)");
            }
        }
    }

    public function down(): void
    {
        Schema::table('agent_versions', fn (Blueprint $table) => $table->dropColumn(['rollout', 'release_notes']));
        foreach (['agent_test_runs', 'agent_test_cases', 'support_sessions', 'protected_terms', 'user_imports', 'invitations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
