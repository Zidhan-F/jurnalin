<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('pkl_period_id')->constrained('pkl_periods')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->string('department_divisi', 100)->nullable();
            $table->string('industry_mentor_name')->nullable();
            $table->string('industry_mentor_phone', 24)->nullable();
            $table->time('work_start_time')->default('08:00:00');
            $table->time('work_end_time')->default('16:00:00');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();

            $table->index(['school_id', 'teacher_id', 'status']);
            $table->index(['school_id', 'student_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX idx_placements_one_active_per_period ON placements (school_id, pkl_period_id, student_id) WHERE status = 'active'");
        }

        Schema::create('attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('placement_id')->constrained('placements')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 32);
            $table->timestampTz('check_in_at')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->integer('check_in_distance_m')->nullable();
            $table->integer('check_in_gps_accuracy_m')->nullable();
            $table->text('check_in_photo_path')->nullable();
            $table->boolean('is_within_radius')->default(true);
            $table->timestampTz('check_out_at')->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->integer('check_out_distance_m')->nullable();
            $table->text('check_out_photo_path')->nullable();
            $table->text('exception_reason')->nullable();
            $table->foreignUuid('approved_by_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['school_id', 'placement_id', 'attendance_date']);
            $table->index(['school_id', 'attendance_date', 'placement_id']);
        });

        Schema::create('journals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('placement_id')->constrained('placements')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->date('journal_date');
            $table->string('work_unit', 100);
            $table->string('activity_title', 200);
            $table->text('activity_description');
            $table->text('tools_materials')->nullable();
            $table->text('learning_outcome')->nullable();
            $table->text('photo_path')->nullable();
            $table->unsignedInteger('duration_minutes')->default(480);
            $table->string('status', 32)->default('submitted');
            $table->text('teacher_feedback')->nullable();
            $table->foreignUuid('validated_by_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampsTz();

            $table->index(['school_id', 'status', 'journal_date']);
            $table->index(['school_id', 'placement_id', 'journal_date']);
        });

        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('placement_id')->constrained('placements')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->json('school_scores');
            $table->json('industry_scores');
            $table->text('industry_form_photo_path')->nullable();
            $table->decimal('attendance_score', 5, 2)->default(0);
            $table->decimal('final_score', 5, 2)->default(0);
            $table->string('predicate', 32);
            $table->text('teacher_notes')->nullable();
            $table->text('industry_notes')->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();

            $table->unique(['school_id', 'placement_id']);
            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('placements');
    }
};
