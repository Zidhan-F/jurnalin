<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas per periode (daftar siswa disalin saat periode disusun, tidak berubah saat siswa naik kelas)
 * dan grup penempatan (satu tempat PKL + satu guru pembimbing untuk sekumpulan siswa).
 */
return new class extends Migration
{
    private const TABLES = ['period_classes', 'period_class_members', 'placement_groups'];

    public function up(): void
    {
        Schema::create('period_classes', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('pkl_period_id')->constrained()->cascadeOnDelete();
            $t->string('name', 64);
            $t->string('major', 100)->nullable();
            $t->timestampsTz();
            $t->unique(['pkl_period_id', 'name']);
            $t->unique(['id', 'school_id', 'pkl_period_id']);
        });

        Schema::create('period_class_members', function (Blueprint $t): void {
            $t->uuid('period_class_id');
            $t->uuid('school_id');
            $t->uuid('pkl_period_id');
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->foreign(['period_class_id', 'school_id', 'pkl_period_id'], 'period_class_members_class_fk')
                ->references(['id', 'school_id', 'pkl_period_id'])->on('period_classes')->cascadeOnDelete();
            // Satu siswa hanya di satu kelas per periode.
            $t->primary(['pkl_period_id', 'student_id']);
            $t->index('period_class_id');
        });

        Schema::create('placement_groups', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('pkl_period_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->foreignUuid('company_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('teacher_id')->constrained()->restrictOnDelete();
            $t->string('department_divisi', 100)->nullable();
            $t->time('work_start_time')->default('08:00:00');
            $t->time('work_end_time')->default('16:00:00');
            $t->timestampsTz();
            $t->unique(['pkl_period_id', 'name']);
            $t->index(['school_id', 'pkl_period_id']);
        });

        Schema::table('placements', function (Blueprint $t): void {
            $t->foreignUuid('placement_group_id')->nullable()->constrained('placement_groups')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (self::TABLES as $table) {
                DB::unprepared("
                    ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                    ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;
                    CREATE POLICY tenant_isolation_policy ON {$table}
                        USING (app_is_super_admin() OR school_id = app_current_school_id())
                        WITH CHECK (app_is_super_admin() OR school_id = app_current_school_id());
                ");
            }
        }
    }

    public function down(): void
    {
        Schema::table('placements', fn (Blueprint $t) => $t->dropConstrainedForeignId('placement_group_id'));
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
