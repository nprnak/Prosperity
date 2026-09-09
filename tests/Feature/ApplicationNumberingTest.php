<?php

namespace Tests\Feature;

use App\Services\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationNumberingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The Nepali fiscal year turns on Shrawan 1, which falls in mid-July — not
     * on 1 July. Approximating the boundary with the Gregorian month stamps a
     * fortnight of applications each year with the wrong fiscal year, and an
     * application number is not something you can quietly reissue.
     */
    public function test_fiscal_year_label_turns_on_shrawan_not_the_gregorian_month(): void
    {
        $numbers = app(NumberGeneratorService::class);

        // 16 July 2026 is Ashad 32, 2083 — the last day of FY 2082/83.
        $this->assertStringStartsWith(
            'PHL-2082-',
            $numbers->generateApplicationNumber('PHL', Carbon::parse('2026-07-16')),
        );

        // 17 July 2026 is Shrawan 1, 2083 — the first day of FY 2083/84.
        $this->assertStringStartsWith(
            'PHL-2083-',
            $numbers->generateApplicationNumber('PHL', Carbon::parse('2026-07-17')),
        );
    }

    public function test_fiscal_year_label_holds_either_side_of_the_new_year(): void
    {
        $numbers = app(NumberGeneratorService::class);

        // Poush 2083 — mid fiscal year 2083/84.
        $this->assertStringStartsWith(
            'PHL-2083-',
            $numbers->generateApplicationNumber('PHL', Carbon::parse('2027-01-10')),
        );

        // Baisakh 2084 — the BS year has rolled over, but the fiscal year
        // has not; it still runs to Ashad end.
        $this->assertStringStartsWith(
            'PHL-2083-',
            $numbers->generateApplicationNumber('PHL', Carbon::parse('2027-04-20')),
        );
    }

    public function test_the_sequence_is_per_fiscal_year(): void
    {
        $numbers = app(NumberGeneratorService::class);

        $first = $numbers->generateApplicationNumber('PHL', Carbon::parse('2026-08-01'));
        $second = $numbers->generateApplicationNumber('PHL', Carbon::parse('2026-08-02'));

        $this->assertSame('PHL-2083-000001', $first);
        $this->assertSame('PHL-2083-000002', $second);

        // A different fiscal year starts its own count.
        $this->assertSame(
            'PHL-2084-000001',
            $numbers->generateApplicationNumber('PHL', Carbon::parse('2027-08-01')),
        );
    }
}
