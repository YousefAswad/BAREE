<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = BAREE global
            $table->string('kind', 16); // article, tip, video, news, tool, story, event
            $table->json('title');
            $table->json('body')->nullable();
            $table->json('targeting')->nullable(); // {"roles":[],"levels":[],"gap":{"competency":"responsible_ai","below":60}}
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->constrained('users');
            $table->timestamps();
        });

        // Append-only audit trail (enforced in the model and, on PostgreSQL, by a trigger).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->string('action', 64);
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('posts');
    }
};
