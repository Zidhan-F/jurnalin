<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Paket per sekolah yang bisa diganti admin sekolah: turun paket terjadwal, naik paket lewat tagihan selisih.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $t): void {
            $t->string('scheduled_plan', 16)->nullable();
            $t->date('scheduled_plan_on')->nullable();
        });

        Schema::table('school_payments', function (Blueprint $t): void {
            $t->string('kind', 16)->default('group'); // group | monthly (lama) | upgrade (selisih naik ke Pro)
        });
        DB::statement("UPDATE school_payments SET kind = 'monthly' WHERE billing_group_id IS NULL");
        DB::statement('DROP INDEX IF EXISTS school_payment_legacy_cycle');
        DB::statement("CREATE UNIQUE INDEX school_payment_legacy_cycle ON school_payments (school_id, pkl_period_id, cycle) WHERE kind = 'monthly'");
        DB::statement("CREATE UNIQUE INDEX school_payment_pending_upgrade ON school_payments (school_id) WHERE kind = 'upgrade' AND status = 'pending'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS school_payment_pending_upgrade');
        DB::statement('DROP INDEX IF EXISTS school_payment_legacy_cycle');
        DB::statement('CREATE UNIQUE INDEX school_payment_legacy_cycle ON school_payments (school_id, pkl_period_id, cycle) WHERE billing_group_id IS NULL');
        Schema::table('school_payments', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('schools', fn (Blueprint $t) => $t->dropColumn(['scheduled_plan', 'scheduled_plan_on']));
    }
};
