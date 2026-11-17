<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TENANT_TABLES = [
        'teachers',
        'students',
        'companies',
        'pkl_periods',
        'placements',
        'attendances',
        'journals',
        'assessments',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION app_current_school_id() RETURNS uuid AS $$
              SELECT NULLIF(current_setting('app.current_school_id', true), '')::uuid;
            $$ LANGUAGE sql STABLE;

            CREATE OR REPLACE FUNCTION app_is_super_admin() RETURNS boolean AS $$
              SELECT COALESCE(current_setting('app.is_super_admin', true), 'false') = 'true';
            $$ LANGUAGE sql STABLE;

            ALTER TABLE users ENABLE ROW LEVEL SECURITY;
            ALTER TABLE users FORCE ROW LEVEL SECURITY;
            CREATE POLICY users_tenant_or_auth_policy ON users
                USING (
                    app_is_super_admin()
                    OR school_id = app_current_school_id()
                    OR email = NULLIF(current_setting('app.auth_lookup_email', true), '')
                    OR id::text = NULLIF(current_setting('app.auth_lookup_user_id', true), '')
                )
                WITH CHECK (
                    app_is_super_admin()
                    OR school_id = app_current_school_id()
                );
        SQL);

        foreach (self::TENANT_TABLES as $table) {
            DB::unprepared("
                ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;
                CREATE POLICY tenant_isolation_policy ON {$table}
                    USING (app_is_super_admin() OR school_id = app_current_school_id())
                    WITH CHECK (app_is_super_admin() OR school_id = app_current_school_id());
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TENANT_TABLES as $table) {
            DB::unprepared("
                DROP POLICY IF EXISTS tenant_isolation_policy ON {$table};
                ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY;
                ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;
            ");
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS users_tenant_or_auth_policy ON users;
            ALTER TABLE users NO FORCE ROW LEVEL SECURITY;
            ALTER TABLE users DISABLE ROW LEVEL SECURITY;
            DROP FUNCTION IF EXISTS app_is_super_admin();
            DROP FUNCTION IF EXISTS app_current_school_id();
        SQL);
    }
};
