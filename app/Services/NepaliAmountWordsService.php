<?php

namespace App\Services;

class NepaliAmountWordsService
{
    public function toWords(string|float|int $amount): string
    {
        $number = (int) floor((float) $amount);

        if ($number === 0) {
            return 'Sunya Rupaiya Matra';
        }

        $units = [
            0 => '', 1 => 'Ek', 2 => 'Dui', 3 => 'Tin', 4 => 'Char', 5 => 'Paanch',
            6 => 'Chha', 7 => 'Saat', 8 => 'Aath', 9 => 'Nau', 10 => 'Das',
            11 => 'Eghaar', 12 => 'Baar', 13 => 'Terha', 14 => 'Chaudha', 15 => 'Pandhra',
            16 => 'Sorah', 17 => 'Satra', 18 => 'Athara', 19 => 'Unnaiis', 20 => 'Bis',
        ];

        if ($number <= 20) {
            return $units[$number].' Rupaiya Matra';
        }

        return number_format($number).' Rupaiya Matra';
    }

    /**
     * English words using the Nepali numbering system (crore/lakh),
     * e.g. 1500000 -> "Fifteen Lakh" — used on the payment receipt as
     * "Nepalese Rupees Fifteen Lakh Only".
     */
    public function toEnglishWords(string|float|int $amount): string
    {
        $number = (int) floor((float) $amount);

        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        foreach ([['Crore', 10000000], ['Lakh', 100000], ['Thousand', 1000], ['Hundred', 100]] as [$label, $value]) {
            if ($number >= $value) {
                $parts[] = $this->englishBelowHundred(intdiv($number, $value)).' '.$label;
                $number %= $value;
            }
        }

        if ($number > 0) {
            $parts[] = $this->englishBelowHundred($number);
        }

        return implode(' ', $parts);
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
}
