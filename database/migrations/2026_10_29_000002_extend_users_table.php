<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_platform_admin')->default(false); // BAREE staff only; never mass assignable
            $table->string('status', 16)->default('invited'); // invited, active, disabled
            $table->string('locale', 8)->default('ar');
            $table->json('profile')->nullable(); // years, grades, subjects, responsibilities
            $table->timestamp('last_active_at')->nullable();
            $table->index(['tenant_id', 'department_id']);
        });

        Schema::create('segment_user', function (Blueprint $table) {
            $table->foreignId('segment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['segment_id', 'user_id']);
        });

        // Explicit, revocable consents (e.g. "my manager may see my individual level").
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->boolean('granted');
            $table->timestamp('decided_at');
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
        Schema::dropIfExists('segment_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('job_title_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('campus_id');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['public_id', 'is_platform_admin', 'status', 'locale', 'profile', 'last_active_at']);
        });
    }
};
