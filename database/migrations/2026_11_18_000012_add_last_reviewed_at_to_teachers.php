<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Patokan interval bergulir jadwal periksa jurnal, agar jadwal tidak terikat hari kalender. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', fn (Blueprint $t) => $t->timestampTz('last_reviewed_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('teachers', fn (Blueprint $t) => $t->dropColumn('last_reviewed_at'));
    }
};
