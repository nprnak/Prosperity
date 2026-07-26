<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move existing payments onto the deposit rows, collapsing an application's
 * several transactions into the one receipt they should always have been.
 *
 * Two rules keep this from rewriting history:
 *
 *   - A transaction that already has a voucher has already produced a printed
 *     receipt carrying its number. It survives untouched and keeps its own
 *     deposit, because renumbering a receipt someone is holding is not a
 *     migration, it is a forgery.
 *   - Everything else merges into the survivor: the transaction with a
 *     voucher if there is one, otherwise the earliest.
 *
 * Non-destructive by design — merged transactions are soft-deleted, so the
 * rows are still there if any of this needs unpicking.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The next migration drops the columns read below. Guarding on one of
        // them keeps this a no-op if it is ever run out of order or twice,
        // rather than failing the whole migration run.
        if (! Schema::hasColumn('payment_transactions', 'payment_reference_no')) {
            return;
        }

        $columns = ['id', 'share_application_id', 'amount', 'bank_name', 'payment_reference_no',
            'cheque_no', 'payment_date', 'verification_status', 'verified_by', 'verified_at', 'notes', 'created_at'];

        $transactions = DB::table('payment_transactions')
            ->whereNull('deleted_at')
            ->select($columns)
            ->orderBy('id')
            ->get()
            ->groupBy('share_application_id');

        $withVouchers = DB::table('vouchers')
            ->whereNull('deleted_at')
            ->pluck('payment_transaction_id')
            ->flip();

        foreach ($transactions as $group) {
            $receipted = $group->filter(fn ($row) => $withVouchers->has($row->id));
            $survivor = $receipted->first() ?? $group->first();

            foreach ($group as $transaction) {
                // A receipted transaction other than the survivor stands alone.
                if ($transaction->id !== $survivor->id && $withVouchers->has($transaction->id)) {
                    $this->deposit($transaction, $transaction->id);

                    continue;
                }

                $this->deposit($transaction, $survivor->id);

                if ($transaction->id !== $survivor->id) {
                    DB::table('payment_transactions')
                        ->where('id', $transaction->id)
                        ->update(['deleted_at' => now()]);
                }
            }

            // The receipt's figure is the sum of the deposits it acknowledges.
            $total = DB::table('payment_deposits')
                ->where('payment_transaction_id', $survivor->id)
                ->sum('amount');

            DB::table('payment_transactions')
                ->where('id', $survivor->id)
                ->update(['amount' => $total]);
        }
    }

    private function deposit(object $transaction, int $belongsTo): void
    {
        DB::table('payment_deposits')->insert([
            'payment_transaction_id' => $belongsTo,
            'bank_name' => $transaction->bank_name,
            'reference_no' => $transaction->payment_reference_no,
            'cheque_no' => $transaction->cheque_no,
            'amount' => $transaction->amount,
            'payment_date' => $transaction->payment_date,
            'verification_status' => $transaction->verification_status,
            'verified_by' => $transaction->verified_by,
            'verified_at' => $transaction->verified_at,
            'notes' => $transaction->notes,
            'created_at' => $transaction->created_at,
            'updated_at' => now(),
        ]);
    }

    /**
     * Deliberately empty. The deposits table is dropped by its own migration,
     * and the transactions this soft-deleted are still on record — guessing at
     * how to un-merge them would risk more than it recovers.
     */
    public function down(): void {}
};
