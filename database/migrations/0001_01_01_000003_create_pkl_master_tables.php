<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nip_nik', 32)->nullable();
            $table->string('specialization')->nullable();
            $table->unsignedInteger('max_mentees')->default(20);
            $table->timestampsTz();

            $table->index('school_id');
        });

        Schema::create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nisn', 16);
            $table->string('nis', 24)->nullable();
            $table->string('class_name', 64);
            $table->string('major', 100);
            $table->char('gender', 1)->nullable();
            $table->string('parent_phone', 24)->nullable();
            $table->timestampsTz();

            $table->unique(['school_id', 'nisn']);
            $table->index(['school_id', 'class_name']);
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('industry_type', 64);
            $table->text('address');
            $table->string('city');
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone', 24)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('geofence_radius_m')->nullable();
            $table->string('npwp', 32)->nullable();
            $table->string('google_place_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['school_id', 'is_active']);
        });

        Schema::create('pkl_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('academic_year', 16);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('min_required_days')->default(120);
            $table->json('assessment_template');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkl_periods');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('students');
        Schema::dropIfExists('teachers');
    }
};
