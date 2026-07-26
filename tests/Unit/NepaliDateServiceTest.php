<?php

namespace Tests\Unit;

use App\Services\NepaliDateService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
// The Laravel base case, not PHPUnit's: the converter reads config(), so it
// needs the container booted.
use Tests\TestCase;

/**
 * Bikram Sambat conversion.
 *
 * The anchors are Baisakh 1 — Nepali New Year — which falls on 13 or 14 April
 * and is the easiest date to check against any published calendar. A converter
 * that gets these right cannot be off by a whole month or year, and the
 * round-trip catches drift within a year.
 */
class NepaliDateServiceTest extends TestCase
{
    private NepaliDateService $dates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dates = new NepaliDateService;
    }

    public static function newYearDates(): array
    {
        return [
            'BS 2079' => ['2022-04-14', '२०७९/०१/०१'],
            'BS 2080' => ['2023-04-14', '२०८०/०१/०१'],
            'BS 2081' => ['2024-04-13', '२०८१/०१/०१'],
            'BS 2082' => ['2025-04-14', '२०८२/०१/०१'],
        ];
    }

    #[DataProvider('newYearDates')]
    public function test_nepali_new_year_converts_exactly(string $gregorian, string $expected): void
    {
        $this->assertSame($expected, $this->dates->toBikramSambat(CarbonImmutable::parse($gregorian)));
    }

    public function test_the_day_before_new_year_is_the_last_day_of_the_old_year(): void
    {
        // Chaitra 2080 ran to 30 days; other years' Chaitra runs to 31. That the
        // length varies year to year is exactly why this table is not written by
        // hand — so the rollover is asserted as a property too, not just a value.
        $eve = CarbonImmutable::parse('2024-04-12');

        $this->assertSame('2080/12/30', $this->dates->toBikramSambat($eve, devanagari: false));
        $this->assertSame('2081/01/01', $this->dates->toBikramSambat($eve->addDay(), devanagari: false));
    }

    public function test_latin_numerals_are_available(): void
    {
        $this->assertSame(
            '2081/01/01',
            $this->dates->toBikramSambat(CarbonImmutable::parse('2024-04-13'), devanagari: false),
        );
    }

    public function test_the_format_is_configurable(): void
    {
        $this->assertSame(
            '२०८२-०४-३०',
            $this->dates->toBikramSambat(CarbonImmutable::parse('2025-08-15'), 'Y-m-d'),
        );
    }

    public function test_a_null_date_converts_to_null(): void
    {
        $this->assertNull($this->dates->toBikramSambat(null));
    }

    public function test_dates_outside_the_supported_range_are_marked_gregorian(): void
    {
        // Never silently presented as BS — a wrong date on a share register is
        // worse than an obviously foreign one.
        foreach (['1900-01-01', '2040-01-01'] as $gregorian) {
            $converted = $this->dates->toBikramSambat(CarbonImmutable::parse($gregorian));

            $this->assertStringContainsString('ई.सं.', $converted);
            $this->assertStringContainsString($gregorian, $converted);
        }
    }

    public function test_the_supported_range_boundaries_are_reported_honestly(): void
    {
        $this->assertTrue($this->dates->supports(CarbonImmutable::parse('1944-06-01')));
        $this->assertTrue($this->dates->supports(CarbonImmutable::parse('2033-06-01')));
        $this->assertFalse($this->dates->supports(CarbonImmutable::parse('1943-12-31')));
        $this->assertFalse($this->dates->supports(CarbonImmutable::parse('2034-01-01')));
        $this->assertFalse($this->dates->supports(null));
    }

    public function test_every_day_of_a_year_converts_to_a_distinct_ascending_date(): void
    {
        // Catches a month-length table that is short or long by a day: any
        // duplicate or backwards step would show up here.
        $seen = [];
        $date = CarbonImmutable::parse('2024-04-13');

        for ($day = 0; $day < 365; $day++) {
            $converted = $this->dates->toBikramSambat($date, 'Y-m-d', devanagari: false);

            $this->assertArrayNotHasKey($converted, $seen, "duplicate BS date at {$date->toDateString()}");
            $seen[$converted] = true;

            $date = $date->addDay();
        }

        $keys = array_keys($seen);
        $sorted = $keys;
        sort($sorted);

        $this->assertSame($sorted, $keys, 'BS dates did not ascend with the Gregorian dates');
    }
}
