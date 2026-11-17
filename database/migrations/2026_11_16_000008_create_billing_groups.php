<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_groups', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('pkl_period_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->date('starts_on')->nullable();
            $t->date('paid_until')->nullable();
            $t->date('journal_until')->nullable();
            $t->boolean('stopped')->default(false);
            $t->timestampsTz();
            $t->unique(['id', 'school_id', 'pkl_period_id']);
        });
        Schema::create('billing_group_members', function (Blueprint $t): void {
            $t->uuid('billing_group_id');
            $t->uuid('school_id');
            $t->uuid('pkl_period_id');
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->foreign(['billing_group_id', 'school_id', 'pkl_period_id'], 'group_members_group_fk')
                ->references(['id', 'school_id', 'pkl_period_id'])->on('billing_groups')->cascadeOnDelete();
            $t->primary(['pkl_period_id', 'student_id']);
        });
        foreach (['billing_groups', 'billing_group_members'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_isolation_policy ON {$table} USING (app_is_super_admin() OR school_id = app_current_school_id()) WITH CHECK (app_is_super_admin() OR school_id = app_current_school_id())");
        }
        Schema::table('school_payments', function (Blueprint $t): void {
            $t->foreignUuid('billing_group_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('months')->default(1);
            $t->dropUnique(['school_id', 'pkl_period_id', 'cycle']);
        });
        DB::statement('CREATE UNIQUE INDEX school_payment_legacy_cycle ON school_payments (school_id, pkl_period_id, cycle) WHERE billing_group_id IS NULL');
        DB::statement("CREATE UNIQUE INDEX school_payment_pending_group ON school_payments (billing_group_id) WHERE status = 'pending' AND billing_group_id IS NOT NULL");
        Schema::table('placements', fn (Blueprint $t) => $t->boolean('legacy_access')->default(true));
        DB::statement('ALTER TABLE placements ALTER COLUMN legacy_access SET DEFAULT false');
        Schema::table('schools', fn (Blueprint $t) => $t->boolean('require_group_payment')->default(false));
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('access_version')->default(0));
    }

    public function down(): void
    {
        if (\App\Support\TenantContext::runAsSuperAdmin(fn () => DB::table('school_payments')->whereNotNull('billing_group_id')->exists())) {
            throw new RuntimeException('Simpan riwayat pembayaran grup sebelum membatalkan migrasi ini.');
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('access_version'));
        Schema::table('schools', fn (Blueprint $t) => $t->dropColumn('require_group_payment'));
        Schema::table('placements', fn (Blueprint $t) => $t->dropColumn('legacy_access'));
        DB::statement('DROP INDEX school_payment_legacy_cycle');
        Schema::table('school_payments', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('billing_group_id');
            $t->dropColumn('months');
            $t->unique(['school_id', 'pkl_period_id', 'cycle']);
        });
        Schema::dropIfExists('billing_group_members');
        Schema::dropIfExists('billing_groups');
    }
};
