<?php

namespace App\Enums\Contracts;

/**
 * Backed enums that render to the user in both scripts. The Nepali label is
 * required because the printed share application form (Applications/Show.vue)
 * is Nepali-only.
 */
interface HasLabels
{
    public function labelEn(): string;

    public function labelNp(): string;
}
