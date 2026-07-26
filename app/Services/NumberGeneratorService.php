<?php

namespace App\Services;

use App\Models\NumberingSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    /** Shrawan — the month the Nepali fiscal year opens on. */
    private const FISCAL_YEAR_START_MONTH = 4;

    public function __construct(private readonly NepaliDateService $nepaliDates) {}

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

    /**
     * The code an applicant quotes to credit their application to a focal
     * person. Kept short enough to read off a phone screen and repeat over the
     * phone, since that is how it reaches the applicant.
     */
    public function generateFocalPersonCode(): string
    {
        return sprintf('FP-%04d', $this->nextGlobalNumber('focal_person'));
    }

    private function nextGlobalNumber(string $type): int
    {
        return DB::transaction(function () use ($type) {
            $row = NumberingSequence::query()
                ->where('type', $type)
                ->where('scope', '')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $bootstrap = $type === 'receipt' ? 56 : 0;
                $row = NumberingSequence::create([
                    'type' => $type,
                    'scope' => '',
                    'current_value' => $bootstrap,
                ]);
                $row->refresh();
            }

            $row->increment('current_value');

            return (int) $row->fresh()->current_value;
        });
    }

    /**
     * The fiscal year runs Shrawan 1 to the end of Ashad, so the label is the
     * BS year of the Shrawan it opened on.
     *
     * The Gregorian month is no guide to that boundary: Shrawan 1 falls in
     * mid-July, so treating 1 July as the turn stamps a fortnight of
     * applications each year with the following fiscal year — and an
     * application number, once issued, is not something to quietly reissue.
     */
    private function nepaliFiscalYearLabel(Carbon $date): string
    {
        $bikramSambat = $this->nepaliDates->toBikramSambat($date, 'Y/m/d', devanagari: false);

        if (is_string($bikramSambat) && preg_match('#^(\d{4})/(\d{2})/#', $bikramSambat, $matches)) {
            [, $year, $month] = $matches;

            return (string) ((int) $month >= self::FISCAL_YEAR_START_MONTH
                ? (int) $year
                : (int) $year - 1);
        }

        // Outside the conversion table's range the service hands back a
        // Gregorian date instead. Fall back to the old approximation rather
        // than refuse to issue a number at all.
        $bsYear = $date->year + 57;

        return (string) ($date->month >= 7 ? $bsYear : $bsYear - 1);
    }
}
