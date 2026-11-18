<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Swafoto presensi hanya bukti sementara untuk ditinjau guru: masa simpan bawaan dipendekkan menjadi 7 hari
 * agar penyimpanan tetap ringan. Sekolah masih bisa mengubahnya di menu Privasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE schools ALTER COLUMN selfie_retention_days SET DEFAULT 7');
        \App\Support\TenantContext::runAsSuperAdmin(fn () => DB::table('schools')->where('selfie_retention_days', 730)->update(['selfie_retention_days' => 7]));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE schools ALTER COLUMN selfie_retention_days SET DEFAULT 730');
    }
};
