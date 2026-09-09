<?php

namespace Modules\ApplicantManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum SourceOfFunds: string implements HasLabels
{
    use HasOptions;

    case Salary = 'salary';
    case Dividend = 'dividend';
    case ShareTrading = 'share_trading';
    case PropertySale = 'property_sale';
    case HouseRent = 'house_rent';
    case ForeignEmployment = 'foreign_employment';
    case LoanOrBorrowing = 'loan_or_borrowing';
    case AncestralProperty = 'ancestral_property';
    case Business = 'business';
    case Other = 'other';
    case Savings = 'savings';

    public function labelEn(): string
    {
        return match ($this) {
            self::Salary => 'Salary / Remuneration',
            self::Dividend => 'Dividend',
            self::ShareTrading => 'Share Trading',
            self::PropertySale => 'Sale of Property',
            self::HouseRent => 'House Rent',
            self::ForeignEmployment => 'Foreign Employment',
            self::LoanOrBorrowing => 'Loan or Borrowing',
            self::AncestralProperty => 'Ancestral Property',
            self::Business => 'Business / Trade',
            self::Other => 'Other (please specify)',
            self::Savings => 'Savings',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Salary => 'पारिश्रमिक',
            self::Dividend => 'लाभांस',
            self::ShareTrading => 'शेयर कारोबार',
            self::PropertySale => 'सम्पत्ति विक्री',
            self::HouseRent => 'घर बहाल',
            self::ForeignEmployment => 'बैदेशिक रोजगारी',
            self::LoanOrBorrowing => 'ऋण वा सापटी',
            self::AncestralProperty => 'पैतृक सम्पत्ति',
            self::Business => 'व्यापार/व्यवसाय',
            self::Other => 'अन्य भए (खुलाउने)',
            self::Savings => 'वचत',
        };
    }
}
