<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spesifikasi Fitur v2.0: presensi berkebijakan, izin resmi, jurnal berversi, nilai TP berversi,
 * privasi & persetujuan wali (UU PDP), Mentor Access, Hubin, Magang Guru, UKK, Paspor, Tracer, AI.
 * Semua tabel tenant memakai school_id + RLS FORCE, sama seperti tabel operasional lama.
 */
return new class extends Migration
{
    private array $tenantTables = [];

    public function up(): void
    {
        Schema::table('schools', function (Blueprint $t): void {
            $t->timestampTz('dpa_accepted_at')->nullable();
            $t->string('dpa_version', 16)->nullable();
            $t->string('privacy_notice_version', 16)->default('2026-11');
            $t->unsignedSmallInteger('max_journal_photos')->default(3);
            $t->string('journal_approver', 16)->default('teacher');
            $t->boolean('peta_dudi_opt_in')->default(false);
            $t->unsignedInteger('selfie_retention_days')->default(730);
            $t->boolean('wa_notifications')->default(false);
        });

        Schema::table('pkl_periods', function (Blueprint $t): void {
            $t->string('status', 16)->default('active');
            $t->json('work_days')->nullable();
            $t->json('holidays')->nullable();
            $t->unsignedInteger('late_tolerance_minutes')->nullable();
            $t->boolean('require_location')->default(true);
            $t->boolean('require_selfie')->nullable();
            $t->unsignedSmallInteger('backdate_days')->default(7);
        });

        Schema::table('students', function (Blueprint $t): void {
            $t->date('birth_date')->nullable();
            $t->string('guardian_name', 120)->nullable();
            $t->date('graduated_at')->nullable();
            $t->boolean('alumni_contact_ok')->default(false);
        });

        Schema::table('users', fn (Blueprint $t) => $t->boolean('wa_opt_out')->default(false));

        Schema::table('placements', function (Blueprint $t): void {
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->unsignedInteger('geofence_radius_m')->nullable();
            $t->uuid('mentor_contact_id')->nullable();
        });

        Schema::table('attendances', function (Blueprint $t): void {
            $t->jsonb('flags')->nullable();
            $t->string('review_status', 16)->nullable();
            $t->text('review_note')->nullable();
            $t->timestampTz('reviewed_at')->nullable();
            $t->string('device_hash', 64)->nullable();
            $t->uuid('leave_request_id')->nullable();
        });

        Schema::table('journals', function (Blueprint $t): void {
            $t->text('obstacles')->nullable();
            $t->string('template_key', 64)->nullable();
            $t->jsonb('photo_paths')->nullable();
            $t->boolean('confirmed_done')->default(false);
            $t->unsignedInteger('version')->default(1);
            $t->text('reopened_reason')->nullable();
            $t->timestampTz('reopened_at')->nullable();
        });

        Schema::table('assessments', function (Blueprint $t): void {
            $t->unsignedInteger('version')->default(1);
            $t->text('achievement_description')->nullable();
            $t->jsonb('objective_scores')->nullable();
            $t->string('industry_source', 24)->default('manual_form');
            $t->text('revision_reason')->nullable();
            $t->jsonb('snapshot')->nullable();
            $t->string('snapshot_hash', 64)->nullable();
        });

        Schema::table('companies', function (Blueprint $t): void {
            $t->string('partnership_status', 16)->default('active');
            $t->uuid('relation_manager_user_id')->nullable();
            $t->text('notes')->nullable();
        });

        // ---------- MVP ----------
        $this->tenant('leave_requests', function (Blueprint $t): void {
            $t->foreignUuid('placement_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->string('type', 16);
            $t->date('start_date');
            $t->date('end_date');
            $t->text('reason');
            $t->text('attachment_path')->nullable();
            $t->string('status', 16)->default('submitted');
            $t->foreignUuid('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('decision_note')->nullable();
            $t->timestampTz('decided_at')->nullable();
            $t->index(['school_id', 'status']);
        });

        $this->tenant('visit_reports', function (Blueprint $t): void {
            $t->foreignUuid('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete();
            $t->date('visit_date');
            $t->string('kind', 16)->default('monitoring');
            $t->string('status', 16)->default('done');
            $t->jsonb('placement_ids')->nullable();
            $t->text('student_condition')->nullable();
            $t->text('mentor_discussion')->nullable();
            $t->text('issues')->nullable();
            $t->text('follow_up')->nullable();
            $t->string('follow_up_owner', 120)->nullable();
            $t->date('follow_up_due')->nullable();
            $t->text('photo_path')->nullable();
            $t->boolean('confidential')->default(false);
        });

        $this->tenant('support_tickets', function (Blueprint $t): void {
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('placement_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category', 24);
            $t->string('urgency', 16)->default('normal');
            $t->string('recipient', 16)->default('teacher');
            $t->text('body');
            $t->string('status', 16)->default('open');
            $t->foreignUuid('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->jsonb('activity')->nullable();
            $t->text('resolution')->nullable();
            $t->timestampTz('resolved_at')->nullable();
        });

        $this->tenant('user_notifications', function (Blueprint $t): void {
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 48);
            $t->string('title', 200);
            $t->text('body')->nullable();
            $t->string('url', 300)->nullable();
            $t->timestampTz('read_at')->nullable();
            $t->index(['user_id', 'read_at']);
        });

        $this->tenant('guardian_consents', function (Blueprint $t): void {
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->string('guardian_name', 120)->nullable();
            $t->string('relation', 40)->nullable();
            $t->string('method', 16);
            $t->boolean('covers_location')->default(true);
            $t->boolean('covers_selfie')->default(true);
            $t->string('notice_version', 16);
            $t->foreignUuid('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('evidence_path')->nullable();
            $t->string('token_hash', 64)->nullable()->unique();
            $t->timestampTz('token_expires_at')->nullable();
            $t->timestampTz('given_at')->nullable();
            $t->timestampTz('withdrawn_at')->nullable();
        });

        $this->tenant('data_requests', function (Blueprint $t): void {
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 16);
            $t->text('details');
            $t->string('status', 16)->default('open');
            $t->text('response')->nullable();
            $t->foreignUuid('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('handled_at')->nullable();
        });

        $this->tenant('journal_versions', function (Blueprint $t): void {
            $t->foreignUuid('journal_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('status', 32);
            $t->jsonb('snapshot');
            $t->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason')->nullable();
        });

        $this->tenant('assessment_versions', function (Blueprint $t): void {
            $t->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->jsonb('snapshot');
            $t->string('snapshot_hash', 64);
            $t->text('reason')->nullable();
            $t->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->tenant('import_logs', function (Blueprint $t): void {
            $t->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind', 24);
            $t->string('file_name', 200)->nullable();
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('success')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->jsonb('errors')->nullable();
        });

        // ---------- V1: kompetensi, target, bimbingan, refleksi, bukti kerja ----------
        $this->tenant('learning_objectives', function (Blueprint $t): void {
            $t->string('scope', 16)->default('pkl');
            $t->string('code', 24);
            $t->string('label', 300);
            $t->text('description')->nullable();
            $t->string('major', 100)->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->unique(['school_id', 'scope', 'code']);
        });

        Schema::create('journal_objectives', function (Blueprint $t): void {
            $t->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('journal_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('learning_objective_id')->constrained()->cascadeOnDelete();
            $t->string('status', 16)->default('claimed');
            $t->foreignUuid('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('verified_at')->nullable();
            $t->primary(['journal_id', 'learning_objective_id']);
        });
        $this->tenantTables[] = 'journal_objectives';

        $this->tenant('learning_targets', function (Blueprint $t): void {
            $t->foreignUuid('placement_id')->constrained()->cascadeOnDelete();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->string('indicator', 300)->nullable();
            $t->date('due_date')->nullable();
            $t->string('status', 16)->default('open');
            $t->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->jsonb('evidence_journal_ids')->nullable();
            $t->text('student_note')->nullable();
            $t->text('reviewer_note')->nullable();
            $t->timestampTz('completed_at')->nullable();
        });

        $this->tenant('mentoring_notes', function (Blueprint $t): void {
            $t->foreignUuid('placement_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('author_label', 120)->nullable();
            $t->string('topic', 200);
            $t->text('note');
            $t->string('action_item', 300)->nullable();
            $t->date('due_date')->nullable();
            $t->string('visibility', 16)->default('shared');
            $t->string('status', 16)->default('open');
            $t->text('student_response')->nullable();
            $t->timestampTz('responded_at')->nullable();
            $t->timestampTz('closed_at')->nullable();
        });

        $this->tenant('weekly_reflections', function (Blueprint $t): void {
            $t->foreignUuid('placement_id')->constrained()->cascadeOnDelete();
            $t->date('week_start');
            $t->text('achievements');
            $t->text('difficulties')->nullable();
            $t->text('lessons')->nullable();
            $t->text('support_needed')->nullable();
            $t->text('next_target')->nullable();
            $t->text('teacher_comment')->nullable();
            $t->timestampTz('commented_at')->nullable();
            $t->unique(['placement_id', 'week_start']);
        });

        $this->tenant('work_proofs', function (Blueprint $t): void {
            $t->foreignUuid('placement_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 16)->default('proof');
            $t->string('title', 200);
            $t->text('result');
            $t->text('contribution')->nullable();
            $t->text('team_members')->nullable();
            $t->text('milestones')->nullable();
            $t->jsonb('journal_ids')->nullable();
            $t->text('photo_path')->nullable();
            $t->string('status', 16)->default('submitted');
            $t->foreignUuid('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reviewer_label', 120)->nullable();
            $t->text('review_note')->nullable();
            $t->timestampTz('reviewed_at')->nullable();
            $t->boolean('publish_allowed')->default(false);
        });

        // ---------- Mentor Access & Hubin ----------
        $this->tenant('partner_contacts', function (Blueprint $t): void {
            $t->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('position', 120)->nullable();
            $t->string('division', 120)->nullable();
            $t->string('phone', 24)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('kind', 16)->default('mentor');
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('consent_note', 300)->nullable();
        });

        $this->tenant('guest_access_grants', function (Blueprint $t): void {
            $t->foreignUuid('partner_contact_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 16);
            $t->string('token_hash', 64)->unique();
            $t->text('token')->nullable();
            $t->jsonb('scope');
            $t->boolean('can_review')->default(false);
            $t->boolean('can_evaluate')->default(true);
            $t->timestampTz('expires_at');
            $t->timestampTz('revoked_at')->nullable();
            $t->string('revoked_reason', 300)->nullable();
            $t->timestampTz('last_used_at')->nullable();
            $t->string('otp_hash', 64)->nullable();
            $t->timestampTz('otp_expires_at')->nullable();
            $t->timestampTz('otp_verified_at')->nullable();
            $t->unsignedSmallInteger('otp_attempts')->default(0);
            $t->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->tenant('mentor_evaluations', function (Blueprint $t): void {
            $t->foreignUuid('placement_id')->nullable()->constrained()->cascadeOnDelete();
            $t->uuid('teacher_internship_id')->nullable();
            $t->foreignUuid('partner_contact_id')->constrained()->cascadeOnDelete();
            $t->uuid('grant_id')->nullable();
            $t->jsonb('rubric');
            $t->jsonb('scores');
            $t->text('notes')->nullable();
            $t->string('status', 16)->default('draft');
            $t->timestampTz('statement_accepted_at')->nullable();
            $t->timestampTz('otp_verified_at')->nullable();
            $t->string('content_hash', 64)->nullable();
            $t->timestampTz('finalized_at')->nullable();
            $t->unsignedInteger('version')->default(1);
        });

        $this->tenant('partner_agreements', function (Blueprint $t): void {
            $t->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 16);
            $t->string('reference_no', 120)->nullable();
            $t->string('title', 200);
            $t->date('signed_on')->nullable();
            $t->date('expires_on')->nullable();
            $t->string('pic_name', 120)->nullable();
            $t->string('status', 16)->default('active');
            $t->text('file_path')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedSmallInteger('reminder_days')->default(60);
        });

        $this->tenant('partner_quotas', function (Blueprint $t): void {
            $t->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('pkl_period_id')->nullable()->constrained()->nullOnDelete();
            $t->string('division', 120)->nullable();
            $t->unsignedInteger('capacity');
            $t->string('source', 24)->nullable();
            $t->timestampTz('confirmed_at')->nullable();
            $t->string('notes', 300)->nullable();
        });

        $this->tenant('partner_requests', function (Blueprint $t): void {
            $t->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 24);
            $t->string('token_hash', 64)->unique();
            $t->timestampTz('expires_at');
            $t->string('status', 16)->default('sent');
            $t->jsonb('response')->nullable();
            $t->string('responder_name', 120)->nullable();
            $t->string('responder_position', 120)->nullable();
            $t->timestampTz('responded_at')->nullable();
            $t->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('reviewed_at')->nullable();
        });

        $this->tenant('partner_evaluations', function (Blueprint $t): void {
            $t->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('pkl_period_id')->nullable()->constrained()->nullOnDelete();
            $t->string('evaluator_role', 16);
            $t->foreignUuid('evaluator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->jsonb('scores');
            $t->text('comment')->nullable();
            $t->text('recommendation')->nullable();
        });

        // ---------- Magang Guru ----------
        $this->tenant('teacher_internships', function (Blueprint $t): void {
            $t->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('company_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('partner_contact_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignUuid('reviewer_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $t->string('title', 200);
            $t->text('objectives');
            $t->string('division', 120)->nullable();
            $t->date('start_date');
            $t->date('end_date');
            $t->string('status', 16)->default('active');
            $t->text('report_summary')->nullable();
            $t->text('report_reflection')->nullable();
        });

        $this->tenant('internship_journals', function (Blueprint $t): void {
            $t->foreignUuid('teacher_internship_id')->constrained()->cascadeOnDelete();
            $t->date('journal_date');
            $t->text('work_done');
            $t->text('industry_process')->nullable();
            $t->string('technology', 300)->nullable();
            $t->text('learning')->nullable();
            $t->text('obstacles')->nullable();
            $t->text('potential_application')->nullable();
            $t->text('photo_path')->nullable();
            $t->string('status', 24)->default('submitted');
            $t->text('feedback')->nullable();
            $t->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('reviewed_at')->nullable();
            $t->text('mentor_note')->nullable();
        });

        $this->tenant('transfer_plans', function (Blueprint $t): void {
            $t->foreignUuid('teacher_internship_id')->constrained()->cascadeOnDelete();
            $t->text('practice_learned');
            $t->text('plan');
            $t->text('materials')->nullable();
            $t->date('schedule_date')->nullable();
            $t->string('indicators', 300)->nullable();
            $t->string('owner_name', 120)->nullable();
            $t->text('evidence')->nullable();
            $t->string('status', 16)->default('planned');
            $t->timestampTz('implemented_at')->nullable();
        });

        $this->tenant('dissemination_records', function (Blueprint $t): void {
            $t->foreignUuid('teacher_internship_id')->constrained()->cascadeOnDelete();
            $t->string('title', 200);
            $t->date('held_on');
            $t->unsignedInteger('participants_count')->default(0);
            $t->text('material')->nullable();
            $t->text('feedback')->nullable();
            $t->text('follow_up')->nullable();
        });

        // ---------- UKK ----------
        $this->tenant('ukk_sessions', function (Blueprint $t): void {
            $t->string('name', 200);
            $t->string('academic_year', 16);
            $t->string('concentration', 120);
            $t->string('venue', 200);
            $t->foreignUuid('venue_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->timestampTz('scheduled_at');
            $t->unsignedInteger('quota')->default(30);
            $t->string('status', 16)->default('prepared');
            $t->jsonb('instrument');
            $t->unsignedInteger('instrument_version')->default(1);
            $t->text('minutes')->nullable();
            $t->timestampTz('finalized_at')->nullable();
            $t->foreignUuid('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->tenant('ukk_participants', function (Blueprint $t): void {
            $t->foreignUuid('ukk_session_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->string('participant_number', 32);
            $t->decimal('final_score', 5, 2)->nullable();
            $t->string('predicate', 32)->nullable();
            $t->jsonb('snapshot')->nullable();
            $t->unique(['ukk_session_id', 'student_id']);
        });

        $this->tenant('ukk_scores', function (Blueprint $t): void {
            $t->foreignUuid('ukk_participant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('examiner_contact_id')->nullable()->constrained('partner_contacts')->nullOnDelete();
            $t->foreignUuid('examiner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('instrument_version');
            $t->jsonb('scores');
            $t->text('notes')->nullable();
            $t->timestampTz('confirmed_at')->nullable();
        });

        // ---------- Sertifikat, Paspor, Tracer, Pembekalan ----------
        $this->tenant('certificates', function (Blueprint $t): void {
            $t->string('kind', 16);
            $t->string('number', 120);
            $t->string('verification_code', 32)->unique();
            $t->foreignUuid('placement_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignUuid('ukk_participant_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->string('holder_name', 150);
            $t->jsonb('payload');
            $t->string('status', 16)->default('valid');
            $t->string('revoked_reason', 300)->nullable();
            $t->timestampTz('revoked_at')->nullable();
            $t->foreignUuid('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('issued_at');
        });

        $this->tenant('passports', function (Blueprint $t): void {
            $t->foreignUuid('student_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->nullable()->unique();
            $t->text('token')->nullable();
            $t->jsonb('settings');
            $t->timestampTz('expires_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
        });

        $this->tenant('tracer_campaigns', function (Blueprint $t): void {
            $t->string('name', 200);
            $t->unsignedSmallInteger('cohort_year');
            $t->date('opens_on');
            $t->date('closes_on');
            $t->string('status', 16)->default('open');
            $t->text('intro')->nullable();
        });

        $this->tenant('tracer_responses', function (Blueprint $t): void {
            $t->foreignUuid('tracer_campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->text('token')->nullable();
            $t->jsonb('statuses')->nullable();
            $t->jsonb('answers')->nullable();
            $t->string('status', 16)->default('invited');
            $t->timestampTz('submitted_at')->nullable();
            $t->jsonb('corrections')->nullable();
            $t->unique(['tracer_campaign_id', 'student_id']);
        });

        $this->tenant('readiness_modules', function (Blueprint $t): void {
            $t->string('title', 200);
            $t->string('summary', 300)->nullable();
            $t->text('content');
            $t->string('video_url', 300)->nullable();
            $t->jsonb('quiz');
            $t->unsignedSmallInteger('pass_score')->default(70);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
        });

        $this->tenant('readiness_results', function (Blueprint $t): void {
            $t->foreignUuid('readiness_module_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('score');
            $t->boolean('passed');
            $t->timestampTz('completed_at');
            $t->unique(['readiness_module_id', 'student_id']);
        });

        // ---------- Fondasi SaaS ----------
        $this->tenant('tenant_features', function (Blueprint $t): void {
            $t->string('feature', 32);
            $t->boolean('enabled')->default(true);
            $t->string('source', 16)->default('addon');
            $t->timestampTz('activated_at')->nullable();
            $t->unique(['school_id', 'feature']);
        });

        Schema::create('message_logs', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('school_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignUuid('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('channel', 16);
            $t->string('recipient', 40);
            $t->string('template', 48);
            $t->string('status', 16);
            $t->string('provider_ref', 120)->nullable();
            $t->unsignedInteger('cost')->default(0);
            $t->string('error', 300)->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['school_id', 'created_at']);
        });
        $this->tenantTables[] = 'message_logs';

        Schema::create('ai_requests', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('feature', 32);
            $t->string('model', 64)->nullable();
            $t->unsignedInteger('input_chars')->default(0);
            $t->unsignedInteger('output_chars')->default(0);
            $t->string('status', 16);
            $t->timestampTz('created_at')->useCurrent();
        });
        $this->tenantTables[] = 'ai_requests';

        // Tautan masuk sekali pakai (magic link). Tidak memakai RLS: hanya hash + id, dicari sebelum sesi ada.
        Schema::create('login_tokens', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('user_id')->index();
            $t->string('token_hash', 64)->unique();
            $t->timestampTz('expires_at');
            $t->timestampTz('used_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach ($this->tenantTables as $table) {
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
        Schema::dropIfExists('login_tokens');
        foreach (array_reverse([
            'leave_requests', 'visit_reports', 'support_tickets', 'user_notifications', 'guardian_consents', 'data_requests',
            'journal_versions', 'assessment_versions', 'import_logs', 'learning_objectives', 'journal_objectives',
            'learning_targets', 'mentoring_notes', 'weekly_reflections', 'work_proofs', 'partner_contacts',
            'guest_access_grants', 'mentor_evaluations', 'partner_agreements', 'partner_quotas', 'partner_requests',
            'partner_evaluations', 'teacher_internships', 'internship_journals', 'transfer_plans', 'dissemination_records',
            'ukk_sessions', 'ukk_participants', 'ukk_scores', 'certificates', 'passports', 'tracer_campaigns',
            'tracer_responses', 'readiness_modules', 'readiness_results', 'tenant_features', 'message_logs', 'ai_requests',
        ]) as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn(['partnership_status', 'relation_manager_user_id', 'notes']));
        Schema::table('assessments', fn (Blueprint $t) => $t->dropColumn(['version', 'achievement_description', 'objective_scores', 'industry_source', 'revision_reason', 'snapshot', 'snapshot_hash']));
        Schema::table('journals', fn (Blueprint $t) => $t->dropColumn(['obstacles', 'template_key', 'photo_paths', 'confirmed_done', 'version', 'reopened_reason', 'reopened_at']));
        Schema::table('attendances', fn (Blueprint $t) => $t->dropColumn(['flags', 'review_status', 'review_note', 'reviewed_at', 'device_hash', 'leave_request_id']));
        Schema::table('placements', fn (Blueprint $t) => $t->dropColumn(['latitude', 'longitude', 'geofence_radius_m', 'mentor_contact_id']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('wa_opt_out'));
        Schema::table('students', fn (Blueprint $t) => $t->dropColumn(['birth_date', 'guardian_name', 'graduated_at', 'alumni_contact_ok']));
        Schema::table('pkl_periods', fn (Blueprint $t) => $t->dropColumn(['status', 'work_days', 'holidays', 'late_tolerance_minutes', 'require_location', 'require_selfie', 'backdate_days']));
        Schema::table('schools', fn (Blueprint $t) => $t->dropColumn(['dpa_accepted_at', 'dpa_version', 'privacy_notice_version', 'max_journal_photos', 'journal_approver', 'peta_dudi_opt_in', 'selfie_retention_days', 'wa_notifications']));
    }

    private function tenant(string $table, Closure $columns): void
    {
        Schema::create($table, function (Blueprint $t) use ($columns): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $columns($t);
            $t->timestampsTz();
            $t->index('school_id');
        });
        $this->tenantTables[] = $table;
    }
};
