<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for the hottest queries found while writing phases 1–5 (verify with EXPLAIN under load). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runs', fn (Blueprint $t) => $t->index(['user_id', 'created_at']));
        Schema::table('outputs', fn (Blueprint $t) => $t->index(['workspace_id', 'archived_at', 'updated_at']));
        Schema::table('enrollments', fn (Blueprint $t) => $t->index(['tenant_id', 'mandatory', 'completed_at', 'due_at']));
        Schema::table('posts', fn (Blueprint $t) => $t->index(['status', 'published_at']));
        Schema::table('knowledge_documents', fn (Blueprint $t) => $t->index(['tenant_id', 'status']));
        Schema::table('mission_attempts', fn (Blueprint $t) => $t->index(['user_id', 'mission_id', 'status']));
        Schema::table('competency_scores', fn (Blueprint $t) => $t->index(['user_id', 'recorded_at']));
        Schema::table('invoices', fn (Blueprint $t) => $t->index(['status', 'due_at']));
        Schema::table('users', fn (Blueprint $t) => $t->index(['tenant_id', 'status', 'department_id']));
    }

    public function down(): void
    {
        Schema::table('ai_runs', fn (Blueprint $t) => $t->dropIndex(['user_id', 'created_at']));
        Schema::table('outputs', fn (Blueprint $t) => $t->dropIndex(['workspace_id', 'archived_at', 'updated_at']));
        Schema::table('enrollments', fn (Blueprint $t) => $t->dropIndex(['tenant_id', 'mandatory', 'completed_at', 'due_at']));
        Schema::table('posts', fn (Blueprint $t) => $t->dropIndex(['status', 'published_at']));
        Schema::table('knowledge_documents', fn (Blueprint $t) => $t->dropIndex(['tenant_id', 'status']));
        Schema::table('mission_attempts', fn (Blueprint $t) => $t->dropIndex(['user_id', 'mission_id', 'status']));
        Schema::table('competency_scores', fn (Blueprint $t) => $t->dropIndex(['user_id', 'recorded_at']));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropIndex(['status', 'due_at']));
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex(['tenant_id', 'status', 'department_id']));
    }
};
