<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jadwal guru memeriksa jurnal: daily | weekly (setiap 7 hari) | monthly (setiap 30 hari) | end. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', fn (Blueprint $t) => $t->string('review_frequency', 16)->default('weekly'));
    }

    public function down(): void
    {
        Schema::table('teachers', fn (Blueprint $t) => $t->dropColumn('review_frequency'));
    }
};
