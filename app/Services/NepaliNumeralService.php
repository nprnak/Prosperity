<?php

namespace App\Services;

/**
 * ASCII digits → Devanagari (०-९), for the reports and documents that are
 * filed entirely in Nepali. Kept separate from NepaliDateService: this is a
 * plain character substitution with no calendar logic, needed for amounts,
 * serial numbers and IDs as much as for dates.
 */
class NepaliNumeralService
{
    private const MAP = [
        '0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४',
        '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९',
    ];

    private const REVERSE_MAP = [
        '०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4',
        '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9',
    ];

    /**
     * Converts every ASCII digit in the value to its Devanagari equivalent.
     * Everything else (commas, decimal points, minus signs, letters) passes
     * through unchanged, so a pre-formatted string like "1,234.50" becomes
     * "१,२३४.५०" without needing to be parsed back into a number first.
     */
    public function toDevanagari(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtr((string) $value, self::MAP);
    }

    /**
     * The reverse — needed wherever a value already rendered in Devanagari
     * (a report cell, say) has to be parsed back into a number, since PHP's
     * numeric casts and preg_replace('/[^0-9]/', ...) only ever recognise
     * ASCII digits.
     */
    public function toAscii(string $value): string
    {
        return strtr($value, self::REVERSE_MAP);
    }
}
