<?php

namespace Modules\ApplicantManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum EducationLevel: string implements HasLabels
{
    use HasOptions;

    case NoFormalEducation = 'no_formal_education';
    case Primary = 'primary';
    case Basic = 'basic';
    case Secondary = 'secondary';
    case SeeSlc = 'see_slc';
    case HigherSecondary = 'higher_secondary';
    case Diploma = 'diploma';
    case Bachelors = 'bachelors';
    case Masters = 'masters';
    case MPhil = 'mphil';
    case Phd = 'phd';
    case Other = 'other';

    public function labelEn(): string
    {
        return match ($this) {
            self::NoFormalEducation => 'No Formal Education',
            self::Primary => 'Primary Level (Grade 1–5)',
            self::Basic => 'Basic Level (Grade 6–8)',
            self::Secondary => 'Secondary Level (Grade 9–10)',
            self::SeeSlc => 'SEE / SLC',
            self::HigherSecondary => 'Higher Secondary (+2)',
            self::Diploma => 'Diploma / Technical Education',
            self::Bachelors => "Bachelor's Degree",
            self::Masters => "Master's Degree",
            self::MPhil => 'MPhil',
            self::Phd => 'PhD / Doctorate',
            self::Other => 'Other',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::NoFormalEducation => 'औपचारिक शिक्षा नभएको',
            self::Primary => 'प्राथमिक तह (कक्षा १–५)',
            self::Basic => 'आधारभूत तह (कक्षा ६–८)',
            self::Secondary => 'माध्यमिक तह (कक्षा ९–१०)',
            self::SeeSlc => 'एसईई / एसएलसी',
            self::HigherSecondary => 'उच्च माध्यमिक (+२)',
            self::Diploma => 'डिप्लोमा / प्राविधिक शिक्षा',
            self::Bachelors => 'स्नातक तह',
            self::Masters => 'स्नातकोत्तर तह',
            self::MPhil => 'एमफिल',
            self::Phd => 'विद्यावारिधि / पीएचडी',
            self::Other => 'अन्य',
        };
    }
}
