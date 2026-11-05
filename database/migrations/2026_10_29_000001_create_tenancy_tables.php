<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 32)->default('school'); // school, network, university, training_center, ngo, government
            $table->string('status', 16)->default('trial'); // trial, active, suspended, cancelled
            $table->string('country', 2)->nullable();
            $table->string('default_locale', 8)->default('ar');
            $table->json('branding')->nullable();   // logo path, primary color (the AI Passport keeps BAREE identity)
            $table->json('settings')->nullable();   // privacy mode, leaderboards, allowed email domains, protected names
            $table->foreignId('plan_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->nullable();
            $table->timestamps();
            $table->index('tenant_id');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('kind', 16)->default('academic'); // academic, administrative
            $table->timestamps();
            $table->index(['tenant_id', 'campus_id']);
        });

        // Job titles map the institution's own vocabulary to a BAREE role template (teacher, hr, finance...).
        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role_template_key', 64);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        // Dynamic segments ("فئات"): rule-based or manual groups used to target tools, content and missions.
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('mode', 16)->default('rules'); // rules, manual
            $table->json('rules')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
        Schema::dropIfExists('job_titles');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('campuses');
        Schema::dropIfExists('tenants');
    }
};
