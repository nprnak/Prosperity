<?php

namespace App\Services;

/**
 * Amounts spelled out in the Nepali numbering system — hundred, thousand,
 * lakh, crore, arab, kharab — in romanized Nepali and in English.
 *
 * Both sides recurse on the count of each scale rather than assuming it is
 * under a hundred, so a hundred crore reads "One Arab" instead of running off
 * the end of a lookup table.
 */
class NepaliAmountWordsService
{
    /** Scale words, largest first. Shared shape, one list per language. */
    private const NEPALI_SCALES = [
        ['Kharab', 100000000000],
        ['Arab', 1000000000],
        ['Karod', 10000000],
        ['Lakh', 100000],
        ['Hazar', 1000],
        ['Saya', 100],
    ];

    private const ENGLISH_SCALES = [
        ['Kharab', 100000000000],
        ['Arab', 1000000000],
        ['Crore', 10000000],
        ['Lakh', 100000],
        ['Thousand', 1000],
        ['Hundred', 100],
    ];

    /**
     * Romanized Nepali has its own word for every number to ninety-nine —
     * twenty-one is "Ekkais", not "Bis Ek" — so the table cannot be built from
     * tens and units the way the English one is.
     */
    private const NEPALI_BELOW_HUNDRED = [
        'Sunya', 'Ek', 'Dui', 'Tin', 'Char', 'Panch', 'Chha', 'Saat', 'Aath', 'Nau',
        'Das', 'Eghara', 'Bahra', 'Tehra', 'Chaudha', 'Pandhra', 'Sohra', 'Satra', 'Athara', 'Unnais',
        'Bis', 'Ekkais', 'Bais', 'Teis', 'Chaubis', 'Pachchis', 'Chhabbis', 'Sattais', 'Atthais', 'Unantis',
        'Tis', 'Ekattis', 'Battis', 'Tettis', 'Chautis', 'Paitis', 'Chhattis', 'Saintis', 'Adtis', 'Unanchalis',
        'Chalis', 'Ekchalis', 'Bayalis', 'Tetalis', 'Chauwalis', 'Paitalis', 'Chhyalis', 'Sadchalis', 'Adchalis', 'Unanchas',
        'Pachas', 'Ekaunna', 'Bawanna', 'Trippanna', 'Chauwanna', 'Pachpanna', 'Chhappanna', 'Santawanna', 'Athhanna', 'Unansathi',
        'Saathi', 'Eksathi', 'Basathi', 'Tresathi', 'Chausathi', 'Paisathi', 'Chhyasathi', 'Sadsathi', 'Adsathi', 'Unansattari',
        'Sattari', 'Ekhattar', 'Bahattar', 'Trihattar', 'Chauhattar', 'Pachhattar', 'Chhahattar', 'Sathattar', 'Athhattar', 'Unasi',
        'Asi', 'Ekasi', 'Bayasi', 'Triyasi', 'Chaurasi', 'Pachasi', 'Chhayasi', 'Satasi', 'Athasi', 'Unannabbe',
        'Nabbe', 'Ekanabbe', 'Biyanabbe', 'Triyanabbe', 'Chauranabbe', 'Panchanabbe', 'Chhayanabbe', 'Satanabbe', 'Athanabbe', 'Unansaya',
    ];

    /**
     * Romanized Nepali, as the statutory application form prints it. The
     * " Rupaiya Matra" suffix is stripped by ApplicationWizardController to
     * reuse this for a share count, so it must stay exactly as it is.
     */
    public function toWords(string|float|int $amount): string
    {
        $rupees = $this->rupees($amount);

        if ($rupees === 0) {
            return 'Sunya Rupaiya Matra';
        }

        return $this->spell($rupees, self::NEPALI_SCALES, fn (int $n) => self::NEPALI_BELOW_HUNDRED[$n])
            .' Rupaiya Matra';
    }

    /**
     * English words using the Nepali numbering system (crore/lakh),
     * e.g. 1500000 -> "Fifteen Lakh" — used on the payment receipt as
     * "Nepalese Rupees Fifteen Lakh Only".
     *
     * Paisa are spelled out too when there are any: the receipt prints the
     * figure to two decimals right beside these words, and words that quietly
     * drop the paisa contradict the figure they sit next to.
     */
    public function toEnglishWords(string|float|int $amount): string
    {
        $rupees = $this->rupees($amount);
        $paisa = $this->paisa($amount);

        $words = $rupees > 0
            ? $this->spell($rupees, self::ENGLISH_SCALES, fn (int $n) => $this->englishBelowHundred($n))
            : '';

        if ($paisa > 0) {
            $paisaWords = $this->englishBelowHundred($paisa).' Paisa';

            return $words === '' ? $paisaWords : $words.' and '.$paisaWords;
        }

        return $words === '' ? 'Zero' : $words;
    }

    /**
     * The shorthand a receipt uses to say which deposit was which — 1000000
     * becomes "10L", 15000000 "1.5Cr". Whole rupees only; this annotates a
     * list of references, it does not state the amount.
     */
    public function toShorthand(string|float|int $amount): string
    {
        $rupees = $this->rupees($amount);

        foreach ([['Cr', 10000000], ['L', 100000], ['K', 1000]] as [$suffix, $value]) {
            if ($rupees >= $value) {
                $scaled = $rupees / $value;

                // Trailing zeroes read as false precision on a receipt.
                return rtrim(rtrim(number_format($scaled, 2, '.', ''), '0'), '.').$suffix;
            }
        }

        return (string) $rupees;
    }

    /**
     * Spell a whole number against a scale table.
     *
     * The count of each scale goes back through this method rather than
     * straight to the below-hundred table, so a hundred crore becomes one arab
     * instead of indexing past the end of it.
     *
     * @param  array<int, array{0: string, 1: int}>  $scales
     * @param  callable(int): string  $belowHundred
     */
    private function spell(int $number, array $scales, callable $belowHundred): string
    {
        if ($number === 0) {
            return '';
        }

        if ($number < 100) {
            return $belowHundred($number);
        }

        foreach ($scales as [$label, $value]) {
            if ($number >= $value) {
                return trim(
                    $this->spell(intdiv($number, $value), $scales, $belowHundred)
                    .' '.$label
                    .' '.$this->spell($number % $value, $scales, $belowHundred)
                );
            }
        }

        return $belowHundred($number);
    }

    private function englishBelowHundred(int $number): string
    {
        $ones = [
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
        ];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($number < 20) {
            return $ones[$number];
        }

        return trim($tens[intdiv($number, 10)].' '.$ones[$number % 10]);
    }

    private function rupees(string|float|int $amount): int
    {
        return intdiv($this->toPaisa($amount), 100);
    }

    private function paisa(string|float|int $amount): int
    {
        return $this->toPaisa($amount) % 100;
    }

    /**
     * Parsed as text rather than through a float: money arrives here as a
     * decimal string from the database, and rounding it through binary
     * floating point is how a receipt ends up a paisa out.
     */
    private function toPaisa(string|float|int $amount): int
    {
        $normalized = preg_replace('/[^0-9.]/', '', (string) $amount) ?: '0';
        [$rupees, $paisa] = array_pad(explode('.', $normalized, 2), 2, '0');
        $paisa = str_pad(substr($paisa, 0, 2), 2, '0');

        return ((int) $rupees * 100) + (int) $paisa;
    }
}
