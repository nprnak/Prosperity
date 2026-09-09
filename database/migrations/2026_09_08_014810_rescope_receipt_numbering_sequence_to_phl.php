<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Receipt numbering moves from one global sequence to one sequence per
 * company, so a newly onboarded company starts at 1 instead of continuing
 * PHL's count. The existing global counter belongs to PHL — the company the
 * paper receipt book already in use is for — so it is renamed onto PHL's
 * scope rather than reset, preserving continuity with receipts already issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('numbering_sequences')
            ->where('type', 'receipt')
            ->where('scope', '')
            ->update(['scope' => 'PHL']);
    }

    public function down(): void
    {
        DB::table('numbering_sequences')
            ->where('type', 'receipt')
            ->where('scope', 'PHL')
            ->update(['scope' => '']);
    }
};
