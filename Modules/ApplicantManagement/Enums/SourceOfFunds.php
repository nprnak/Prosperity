<?php

namespace Modules\ApplicantManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum SourceOfFunds: string implements HasLabels
{
    use HasOptions;

    case Salary = 'salary';
    case Dividend = 'dividend';
    case PropertySale = 'property_sale';
    case HouseRent = 'house_rent';
    case ShareTrading = 'share_trading';
    case Other = 'other';

    public function labelEn(): string
    {
        return match ($this) {
            self::Salary => 'Salary / Employment',
            self::Dividend => 'Dividend',
            self::PropertySale => 'Sale of Assets',
            self::HouseRent => 'House Rent',
            self::ShareTrading => 'Share Trading',
            self::Other => 'Other',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Salary => 'तलब',
            self::Dividend => 'लाभांश',
            self::PropertySale => 'सम्पत्ति बिक्री',
            self::HouseRent => 'घर भाडा',
            self::ShareTrading => 'शेयर कारोबार',
            self::Other => 'अन्य',
        };
    }
}
