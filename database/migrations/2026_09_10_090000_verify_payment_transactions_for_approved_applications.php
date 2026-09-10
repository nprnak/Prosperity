<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A payment is now verified automatically once its application is approved
 * (see ApproverController), replacing the old manual per-deposit and
 * two-officer finance sign-off. This backfills that rule onto applications
 * that reached approval before the change, so the transaction record matches
 * what actually happened — the money was accepted — rather than sitting
 * "pending" forever with nothing left able to move it to "verified".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_transactions as pt')
            ->join('share_applications as sa', 'sa.id', '=', 'pt.share_application_id')
            ->whereIn('sa.status', ['approved', 'allotted', 'partially_allotted', 'demat_credited'])
            ->where('pt.verification_status', '!=', 'verified')
            ->whereNull('pt.deleted_at')
            ->update([
                'pt.verification_status' => 'verified',
                'pt.verified_by' => DB::raw('sa.approved_by'),
                'pt.verified_at' => DB::raw('COALESCE(sa.approved_at, NOW())'),
                'pt.checked_by' => DB::raw('COALESCE(pt.checked_by, sa.approved_by)'),
                'pt.checked_at' => DB::raw('COALESCE(pt.checked_at, sa.approved_at, NOW())'),
                'pt.approved_by' => DB::raw('COALESCE(pt.approved_by, sa.approved_by)'),
            ]);
    }

    /**
     * Not reversible: there is no record of which of these transactions were
     * already verified by hand before this ran, so rolling back would have to
     * guess which ones to unverify.
     */
    public function down(): void {}
};
