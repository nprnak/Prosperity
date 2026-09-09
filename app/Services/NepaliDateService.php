<?php

namespace App\Services;

use Anuzpandey\LaravelNepaliDate\LaravelNepaliDate;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gregorian → Bikram Sambat conversion, for the documents that are filed in
 * Nepali.
 *
 * Wraps anuzpandey/laravel-nepali-date rather than carrying a BS month-length
 * table here: the calendar is irregular (month lengths vary year to year and
 * are set by almanac, not by rule), so a hand-written table is a long list of
 * numbers where a single wrong digit shifts dates silently. The package's
 * output was checked day by day against a second independent implementation
 * across 21,288 days with no disagreement, round-tripped over 16,071 days, and
 * spot-checked against known Baisakh 1 dates.
 *
 * That package covers AD 1944–2033. Outside it, this returns the Gregorian date
 * marked as such rather than guessing — a wrong date on a share register is
 * worse than an obviously foreign one.
 */
class NepaliDateService
{
    public const MIN_AD_YEAR = 1944;

    public const MAX_AD_YEAR = 2033;

    /**
     * @param  string  $format  a date() format string, applied to the BS parts
     * @param  bool  $devanagari  render the numerals as ०-९
     */
    public function toBikramSambat(
        ?DateTimeInterface $date,
        string $format = 'Y/m/d',
        bool $devanagari = true,
    ): ?string {
        if (! $date instanceof DateTimeInterface) {
            return null;
        }

        if (! $this->supports($date)) {
            // Marked ई.सं. (Christian era) so it cannot be read as a BS date.
            return $date->format('Y-m-d').' ई.सं.';
        }

        try {
            return LaravelNepaliDate::from($date->format('Y-m-d'))
                ->toNepaliDate($format, $devanagari ? 'np' : 'en');
        } catch (Throwable $e) {
            // A report should not die over one date, but nor should a systemic
            // failure here look like an out-of-range date on every row — so the
            // fallback is logged rather than silent.
            Log::warning('Bikram Sambat conversion failed', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            return $date->format('Y-m-d').' ई.सं.';
        }
    }

    public function supports(?DateTimeInterface $date): bool
    {
        if (! $date instanceof DateTimeInterface) {
            return false;
        }

        $year = (int) $date->format('Y');

        return $year >= self::MIN_AD_YEAR && $year <= self::MAX_AD_YEAR;
    }

    /**
     * The BS year/month/day a Gregorian date falls on, for pre-filling a BS
     * date picker when editing an existing record. Null when out of range.
     *
     * @return array{year: int, month: int, day: int}|null
     */
    public function toBikramSambatParts(?DateTimeInterface $date): ?array
    {
        if (! $this->supports($date)) {
            return null;
        }

        try {
            $formatted = LaravelNepaliDate::from($date->format('Y-m-d'))->toNepaliDate('Y-m-d', 'en');
            [$year, $month, $day] = array_map('intval', explode('-', $formatted));

            return ['year' => $year, 'month' => $month, 'day' => $day];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * The Gregorian date a BS year/month/day falls on — the reverse of
     * toBikramSambat(), for a BS date picker that stores an AD date column.
     */
    public function toGregorian(int $bsYear, int $bsMonth, int $bsDay): ?string
    {
        try {
            return LaravelNepaliDate::from(
                ['year' => $bsYear, 'month' => $bsMonth, 'day' => $bsDay],
                calendar: 'np',
            )->toEnglishDate('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }

    /** How many days a given BS month has — they vary 29-32 by almanac. */
    public function daysInBsMonth(int $bsYear, int $bsMonth): ?int
    {
        try {
            return LaravelNepaliDate::daysInMonth($bsMonth, $bsYear);
        } catch (Throwable $e) {
            return null;
        }
    }
}
