<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();      // anthropic, openai, openrouter, ollama ...
            $table->string('driver', 32);             // anthropic, openai_compatible, fake
            $table->string('base_url')->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('data_policy')->nullable();  // {"zero_retention":true,"no_training":true}
            $table->timestamps();
        });

        // Platform keys (tenant_id null) or institution "bring your own key". Always encrypted at rest.
        Schema::create('ai_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('secret'); // encrypted cast
            $table->string('label')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'tenant_id']);
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_key');
            $table->string('tier', 16)->default('standard'); // light, standard, advanced
            $table->json('capabilities')->nullable();          // ["text","vision","files"]
            $table->decimal('input_cost_per_mtok', 10, 4)->nullable();
            $table->decimal('output_cost_per_mtok', 10, 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['provider_id', 'model_key']);
        });

        Schema::create('prompt_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->unsignedInteger('version');
            $table->string('group', 32);
            $table->text('content');
            $table->string('status', 16)->default('published');
            $table->timestamps();
            $table->unique(['key', 'version']);
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = BAREE agent
            $table->string('key', 64);
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('category', 32);
            $table->json('role_templates')->nullable(); // who may use it by default
            $table->string('status', 16)->default('draft');
            $table->foreignId('published_version_id')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('agent_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('system_prompt');                 // Layer 1: trained by BAREE, never shown to tenants
            $table->json('prompt_block_keys')->nullable(); // shared blocks injected after the system prompt
            $table->json('input_schema');                  // fields rendered as a guided form
            $table->json('output_schema')->nullable();
            $table->json('routing');                       // {"providers":["anthropic","openai"],"tier":"standard","max_tokens":4000}
            $table->json('customization_policy');          // what tenants and users may customise
            $table->unsignedInteger('credits_cost');
            $table->string('status', 16)->default('draft'); // draft, testing, published, retired
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['agent_id', 'version']);
        });

        // Layer 2: institution rules and access per agent.
        Schema::create('tenant_agent_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->json('department_ids')->nullable(); // null = all departments
            $table->text('institution_rules')->nullable();
            $table->unsignedInteger('monthly_credit_cap')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'agent_id']);
        });

        // Layer 4: personal instructions per user and agent.
        Schema::create('user_agent_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'agent_id']);
        });

        Schema::create('knowledge_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->morphs('owner'); // agent, tenant, workspace, user
            $table->string('disk', 32)->default('s3');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('usage_mode', 16)->default('on_demand'); // always, on_demand, off
            $table->string('status', 16)->default('pending');        // pending, scanned, indexed, rejected
            $table->timestamps();
        });

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_version_id')->constrained();
            $table->foreignId('workspace_id')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('model')->nullable();
            $table->string('status', 16)->default('queued'); // queued, running, succeeded, failed
            $table->json('inputs');                         // already redacted
            $table->longText('output')->nullable();         // redacted form; restored only at render time
            $table->text('redaction_map')->nullable();      // encrypted array cast
            $table->unsignedInteger('redactions_count')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('credits')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('attempts')->nullable();           // failover trail
            $table->string('error')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['ai_runs', 'knowledge_files', 'user_agent_preferences', 'tenant_agent_settings', 'agent_versions',
            'agents', 'prompt_blocks', 'ai_models', 'ai_provider_credentials', 'ai_providers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
