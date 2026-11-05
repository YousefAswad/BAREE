<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- Content & feed ----------
        Schema::table('posts', function (Blueprint $table) {
            $table->string('status', 16)->default('draft'); // draft, scheduled, published, archived
            $table->string('cta_url')->nullable();
            $table->timestamp('pinned_until')->nullable();
        });

        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamp('clicked_at')->nullable();
            $table->unique(['user_id', 'post_id']);
        });

        // ---------- Notifications ----------
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 48);   // assignment, achievement, institution, tools, billing
            $table->string('channel', 16); // database, mail, whatsapp
            $table->boolean('enabled');
            $table->unique(['user_id', 'type', 'channel']);
        });

        // ---------- Analytics ----------
        Schema::create('user_activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unique(['user_id', 'day']);
            $table->index(['tenant_id', 'day']);
        });

        Schema::create('readiness_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable();
            $table->date('month');
            $table->unsignedInteger('people');
            $table->decimal('readiness', 5, 2);
            $table->json('levels');
            $table->unique(['tenant_id', 'department_id', 'month']);
        });

        // ---------- Billing ----------
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('price_per_seat_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('interval', 8)->default('year'); // month, year
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 16); // trialing, active, past_due, canceled
            $table->unsignedInteger('seats');
            $table->string('currency', 3);
            $table->unsignedInteger('price_per_seat_cents');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->foreignId('next_plan_id')->nullable(); // changes apply at renewal
            $table->unsignedInteger('next_seats')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->string('provider', 16)->default('manual'); // stripe, manual, hmac
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 32)->unique();
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->string('status', 16)->default('open'); // open, paid, void, uncollectible
            $table->json('lines');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('provider', 16)->nullable();
            $table->string('provider_ref')->nullable();
            $table->timestamps();
        });

        // Webhook idempotency: each provider event is processed once.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->string('event_id', 120);
            $table->string('type', 64);
            $table->timestamp('processed_at');
            $table->unique(['provider', 'event_id']);
        });

        // ---------- WhatsApp ----------
        Schema::create('phone_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('phone');                    // encrypted E.164
            $table->string('phone_hash', 64)->unique(); // HMAC for inbound lookup
            $table->string('otp_hash', 64)->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable(); // 24-hour customer service window
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('direction', 8); // in, out
            $table->string('wa_message_id', 128)->nullable()->unique();
            $table->string('kind', 16);     // template, text
            $table->string('template', 64)->nullable();
            $table->string('status', 16)->default('sent'); // sent, delivered, read, failed, received
            $table->timestamp('created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['post_views', 'user_activity_days', 'readiness_snapshots', 'subscriptions', 'invoices', 'phone_links'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint) WITH CHECK (current_setting('app.bypass_rls', true) = 'on' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint)");
            }
        }
    }

    public function down(): void
    {
        foreach (['whatsapp_messages', 'phone_links', 'payment_events', 'invoices', 'subscriptions', 'readiness_snapshots', 'user_activity_days', 'notification_preferences', 'post_views'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn(['price_per_seat_cents', 'currency', 'interval']));
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['status', 'cta_url', 'pinned_until']));
    }
};
