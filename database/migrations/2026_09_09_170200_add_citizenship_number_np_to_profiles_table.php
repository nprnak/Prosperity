<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The citizenship number as printed on the certificate, in Devanagari
 * numerals — kept as its own field rather than derived by converting the
 * digit-for-digit English value, since older citizenships were issued with
 * a different grouping in Nepali than the digits alone would produce.
 * Optional: the Share Lagat report falls back to converting the English
 * number when this is blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('citizenship_number_np')->nullable()->after('citizenship_number');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('citizenship_number_np');
        });
    }
};
