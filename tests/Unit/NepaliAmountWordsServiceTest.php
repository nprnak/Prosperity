<?php

namespace Tests\Unit;

use App\Services\NepaliAmountWordsService;
use PHPUnit\Framework\TestCase;

class NepaliAmountWordsServiceTest extends TestCase
{
    private NepaliAmountWordsService $words;

    protected function setUp(): void
    {
        parent::setUp();

        $this->words = new NepaliAmountWordsService;
    }

    /**
     * The statutory form has an "अक्षरेपि" (in words) field. Printing digits
     * into it defeats the entire point of the field, which exists so a figure
     * cannot be altered after signing.
     */
    public function test_amounts_above_twenty_are_words_not_digits(): void
    {
        $this->assertSame('Ekkais Rupaiya Matra', $this->words->toWords(21));
        $this->assertSame('Ek Saya Rupaiya Matra', $this->words->toWords(100));
        $this->assertSame('Pandhra Lakh Rupaiya Matra', $this->words->toWords(1500000));

        foreach ([21, 99, 1000, 150000, 1500000] as $amount) {
            $this->assertDoesNotMatchRegularExpression(
                '/\d/', $this->words->toWords($amount),
                "toWords({$amount}) still contains digits",
            );
        }
    }

    public function test_the_nepali_scale_words_are_used(): void
    {
        $this->assertSame('Sunya Rupaiya Matra', $this->words->toWords(0));
        $this->assertSame('Das Rupaiya Matra', $this->words->toWords(10));
        $this->assertSame('Ek Hazar Rupaiya Matra', $this->words->toWords(1000));
        $this->assertSame('Ek Lakh Pachas Hazar Rupaiya Matra', $this->words->toWords(150000));
        $this->assertSame('Ek Karod Rupaiya Matra', $this->words->toWords(10000000));
    }

    /**
     * ApplicationWizardController strips this exact suffix to reuse the same
     * conversion for a share count, so it must not drift.
     */
    public function test_the_suffix_the_share_count_strips_is_unchanged(): void
    {
        $this->assertStringEndsWith(' Rupaiya Matra', $this->words->toWords(20));
        $this->assertSame('Bis', str_replace(' Rupaiya Matra', '', $this->words->toWords(20)));
    }

    public function test_english_words_use_the_lakh_crore_system(): void
    {
        $this->assertSame('Zero', $this->words->toEnglishWords(0));
        $this->assertSame('Fifteen Lakh', $this->words->toEnglishWords(1500000));
        $this->assertSame('One Lakh Fifty Thousand', $this->words->toEnglishWords(150000));
        $this->assertSame('Twelve Crore Thirty Four Lakh', $this->words->toEnglishWords(123400000));
    }

    /**
     * A hundred crore fed englishBelowHundred() a count of 100 and read past
     * the end of its tens table, so the receipt printed " Crore" beside a
     * warning.
     */
    public function test_amounts_at_and_above_a_hundred_crore_do_not_break(): void
    {
        $this->assertSame('One Arab', $this->words->toEnglishWords(1000000000));
        $this->assertSame('Ek Arab Rupaiya Matra', $this->words->toWords(1000000000));
        $this->assertSame('One Kharab', $this->words->toEnglishWords(100000000000));
    }

    /**
     * The receipt prints the figure to two decimals beside the words, so words
     * that quietly drop the paisa contradict the figure they sit next to.
     */
    public function test_paisa_are_carried_into_the_words(): void
    {
        $this->assertSame(
            'One Lakh Fifty Thousand and Fifty Paisa',
            $this->words->toEnglishWords('150000.50'),
        );
        $this->assertSame('Fifty Paisa', $this->words->toEnglishWords('0.50'));
        $this->assertSame('One Lakh Fifty Thousand', $this->words->toEnglishWords('150000.00'));
    }
}
