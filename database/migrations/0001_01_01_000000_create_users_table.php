<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('npsn', 8)->unique();
            $table->string('name');
            $table->text('address');
            $table->string('city');
            $table->string('province');
            $table->string('timezone', 32)->default('Asia/Jakarta');
            $table->text('logo_path')->nullable();
            $table->json('letterhead_config');
            $table->unsignedInteger('default_geofence_radius_m')->default(200);
            $table->unsignedInteger('late_tolerance_minutes')->default(15);
            $table->boolean('require_attendance_photo')->default(true);
            $table->boolean('require_industry_form_photo')->default(true);
            $table->string('subscription_tier', 32)->default('free_pilot');
            $table->unsignedInteger('max_students_quota')->default(35);
            $table->string('status', 32)->default('pending_verification')->index();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 24)->nullable();
            $table->string('password');
            $table->string('role', 32)->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();

            $table->index(['school_id', 'role']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('schools');
    }
};
