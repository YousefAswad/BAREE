<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Defence in depth: even if an application query forgets the tenant scope, PostgreSQL refuses to return
 * rows from another institution. The app sets `app.tenant_id` per request (see SetTenantContext).
 * Run the app with a database role that does NOT own the tables, otherwise RLS is bypassed.
 */
return new class extends Migration
{
    private array $tables = [
        'campuses', 'departments', 'job_titles', 'segments', 'user_consents', 'assessment_campaigns',
        'assessment_attempts', 'attempt_answers', 'competency_scores', 'passports', 'tenant_agent_settings',
        'user_agent_preferences', 'ai_runs', 'credit_ledger', 'workspaces', 'outputs', 'mission_assignments',
        'mission_attempts',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                USING (
                    current_setting('app.bypass_rls', true) = 'on'
                    OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.bypass_rls', true) = 'on'
                    OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::bigint
                )
            SQL);
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION forbid_audit_mutation() RETURNS trigger AS $$
            BEGIN RAISE EXCEPTION 'audit_logs is append-only'; END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER audit_logs_immutable BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION forbid_audit_mutation();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs; DROP FUNCTION IF EXISTS forbid_audit_mutation();');

        foreach ($this->tables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
