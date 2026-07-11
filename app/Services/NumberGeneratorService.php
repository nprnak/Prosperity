<?php

namespace App\Services;

use App\Models\NumberingSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    public function generateApplicationNumber(?Carbon $date = null): string
    {
        $date = $date ?: now();
        $fy = $this->nepaliFiscalYearLabel($date);

        $sequence = DB::transaction(function () use ($fy) {
            $row = NumberingSequence::query()
                ->where('type', 'application')
                ->where('scope', $fy)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = NumberingSequence::create([
                    'type' => 'application',
                    'scope' => $fy,
                    'current_value' => 0,
                ]);
                $row->refresh();
            }

            $row->increment('current_value');

            return (int) $row->fresh()->current_value;
        });

        return sprintf('PHL-%s-%06d', $fy, $sequence);
    }

    public function generateReceiptNumber(): string
    {
        $sequence = $this->nextGlobalNumber('receipt');

        return str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    public function generateVoucherNumber(): string
    {
        $sequence = $this->nextGlobalNumber('voucher');

        return str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function nextGlobalNumber(string $type): int
    {
        return DB::transaction(function () use ($type) {
            $row = NumberingSequence::query()
                ->where('type', $type)
                ->whereNull('scope')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $bootstrap = $type === 'receipt' ? 56 : 0;
                $row = NumberingSequence::create([
                    'type' => $type,
                    'scope' => null,
                    'current_value' => $bootstrap,
                ]);
                $row->refresh();
            }

            $row->increment('current_value');

            return (int) $row->fresh()->current_value;
        });
    }

    private function nepaliFiscalYearLabel(Carbon $date): string
    {
        // Approximate BS year from AD year (for numbering label consistency).
        $bsYear = $date->year + 57;
        $startYear = $date->month >= 7 ? $bsYear : ($bsYear - 1);

        return (string) $startYear;
    }
}
