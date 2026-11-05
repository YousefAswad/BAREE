<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->json('name');
            $table->json('limits'); // {"users":500,"monthly_credits":80000,"storage_gb":200,"custom_agents":false}
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Append-only credit ledger. Balance = SUM(delta). Never update rows.
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason', 32); // monthly_grant, top_up, ai_run, refund, adjustment
            $table->nullableMorphs('reference');
            $table->foreignId('agent_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
            $table->string('kind', 16); // course, unit, lesson, project
            $table->string('title');
            $table->text('instructions')->nullable(); // Layer 3
            $table->json('memory')->nullable();       // user-editable facts learned in this workspace
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'owner_id']);
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('viewer'); // editor, viewer
            $table->primary(['workspace_id', 'user_id']);
        });

        Schema::create('outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('outputs')->nullOnDelete(); // versioning
            $table->string('type', 32); // lesson_plan, slides, worksheet, quiz, video, model3d ...
            $table->string('title');
            $table->json('content')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->json('title');
            $table->foreignId('agent_id')->nullable()->constrained(); // practice uses the studio agent in sandbox mode
            $table->json('steps');
            $table->json('rubric');                // [{"criterion":"verification","weight":30}]
            $table->json('competency_impact');     // {"data_analysis":4,"verification":2}
            $table->unsignedInteger('xp')->default(100);
            $table->unsignedSmallInteger('minutes')->default(20);
            $table->timestamps();
        });

        Schema::create('mission_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->json('audience');
            $table->boolean('mandatory')->default(false);
            $table->timestamp('due_at')->nullable();
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('mission_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('in_progress');
            $table->json('rubric_scores')->nullable();
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['mission_attempts', 'mission_assignments', 'missions', 'outputs', 'workspace_members', 'workspaces',
            'credit_ledger', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
