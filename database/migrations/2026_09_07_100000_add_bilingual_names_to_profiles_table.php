<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * father/mother/grandfather/spouse names were English-only; the KYC form
     * (and the printed Nepali share-application form) need both languages,
     * matching the full_name_en/full_name_np split the profile already has.
     *
     * The National ID document also gets a front/back pair, matching
     * citizenship — the existing single upload becomes "front" rather than
     * losing what applicants have already uploaded.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->renameColumn('father_name', 'father_name_en');
            $table->renameColumn('mother_name', 'mother_name_en');
            $table->renameColumn('grandfather_name', 'grandfather_name_en');
            $table->renameColumn('spouse_name', 'spouse_name_en');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('father_name_np')->nullable()->after('father_name_en');
            $table->string('mother_name_np')->nullable()->after('mother_name_en');
            $table->string('grandfather_name_np')->nullable()->after('grandfather_name_en');
            $table->string('spouse_name_np')->nullable()->after('spouse_name_en');
        });

        DB::table('profile_documents')
            ->where('document_type', 'national_id')
            ->update(['document_type' => 'national_id_front']);
    }

    public function down(): void
    {
        DB::table('profile_documents')
            ->where('document_type', 'national_id_front')
            ->update(['document_type' => 'national_id']);

        // A national_id_back upload has nowhere to go back to — it is dropped
        // along with the column that required it existing in the first place.
        DB::table('profile_documents')->where('document_type', 'national_id_back')->delete();

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['father_name_np', 'mother_name_np', 'grandfather_name_np', 'spouse_name_np']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->renameColumn('father_name_en', 'father_name');
            $table->renameColumn('mother_name_en', 'mother_name');
            $table->renameColumn('grandfather_name_en', 'grandfather_name');
            $table->renameColumn('spouse_name_en', 'spouse_name');
        });
    }
};
