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
}
