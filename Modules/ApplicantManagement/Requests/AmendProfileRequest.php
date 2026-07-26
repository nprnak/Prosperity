<?php

namespace Modules\ApplicantManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A staff correction to a profile the review chain has already approved.
 *
 * Deliberately narrow. Contact and banking details go stale — people change
 * bank, phone number and demat account — and an approved profile was
 * previously frozen against every one of them, which stranded ASBA blocking
 * and refunds on numbers nobody could fix. Identity is a different matter:
 * name, parentage, date of birth and the citizenship and PAN numbers are what
 * the three stages actually signed off on, so correcting those means going
 * through the chain again rather than editing behind it.
 */
class AmendProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('profile.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'bank_name' => ['sometimes', 'string', 'max:255'],
            'bank_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_branch' => ['sometimes', 'string', 'max:255'],
            'bank_account_number' => ['sometimes', 'string', 'max:100'],
            'account_holder_name' => ['sometimes', 'string', 'max:255'],
            'boid' => ['sometimes', 'string', 'size:16'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
