<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('pkl_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle');
            $table->string('plan', 32);
            $table->unsignedInteger('student_count');
            $table->unsignedInteger('unit_price');
            $table->string('status', 16);
            $table->string('proof_path');
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestampsTz();
            $table->unique(['school_id', 'pkl_period_id', 'cycle']);
            $table->index(['status', 'updated_at']);
        });
        DB::unprepared('ALTER TABLE school_payments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE school_payments FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation_policy ON school_payments
            USING (app_is_super_admin() OR school_id = app_current_school_id())
            WITH CHECK (app_is_super_admin() OR school_id = app_current_school_id())');
    }

    public function down(): void
    {
        Schema::dropIfExists('school_payments');
    }
};
