<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->timestampTz('terms_accepted_at')->nullable();
            $table->string('mentor_signoff_frequency', 16)->default('weekly'); // daily | weekly | monthly
            $table->timestampTz('deletion_requested_at')->nullable();
        });

        Schema::table('placements', function (Blueprint $table): void {
            // Hash untuk pencarian, token terenkripsi (APP_KEY) agar tautan bisa dibagikan ulang.
            $table->string('mentor_link_hash', 64)->nullable()->unique();
            $table->text('mentor_link_token')->nullable();
            $table->timestampTz('mentor_link_expires_at')->nullable();
        });

        Schema::table('journals', function (Blueprint $table): void {
            $table->text('mentor_note')->nullable();
            $table->timestampTz('mentor_note_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 150)->nullable(); // nama penanda tangan DUDI (tanpa akun)
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['school_id', 'created_at']);
        });

        Schema::create('mentor_signoffs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('placement_id')->constrained('placements')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('signer_name', 120);
            $table->string('signer_title', 120)->nullable();
            $table->string('signature_path')->nullable(); // null bila penanda tangan memakai alternatif tanpa gambar
            $table->string('content_hash', 64);
            $table->jsonb('journal_ids');
            $table->jsonb('attendance_summary');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('signed_at');
            $table->timestampsTz();
            $table->unique(['placement_id', 'period_start']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['audit_logs', 'mentor_signoffs'] as $table) {
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
        Schema::dropIfExists('mentor_signoffs');
        Schema::dropIfExists('audit_logs');
        Schema::table('journals', fn (Blueprint $t) => $t->dropColumn(['mentor_note', 'mentor_note_at']));
        Schema::table('placements', fn (Blueprint $t) => $t->dropColumn(['mentor_link_hash', 'mentor_link_token', 'mentor_link_expires_at']));
        Schema::table('schools', fn (Blueprint $t) => $t->dropColumn(['terms_accepted_at', 'mentor_signoff_frequency', 'deletion_requested_at']));
    }
};
