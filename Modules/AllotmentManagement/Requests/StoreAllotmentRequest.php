<?php

namespace Modules\AllotmentManagement\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\AllotmentManagement\Repositories\ShareAllotmentRepository;
use Modules\ApplicationManagement\Models\ShareApplication;

class StoreAllotmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('allotment.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Zero is a real outcome — an unsuccessful applicant — and the
            // only way ApplicationStatus::NotAllotted is ever reached.
            'shares_allotted' => [
                'required', 'integer', 'min:0',
                'max:'.(int) $this->application()->shares_applied,
            ],
            'allotment_date' => ['required', 'date'],
            'demat_account_no' => ['nullable', 'string', 'max:255'],
            'dp_id' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'shares_allotted.max' => 'An applicant cannot be allotted more shares than they applied for ('
                .(int) $this->application()->shares_applied.').',
        ];
    }

    /**
     * The offering's own ceiling. Allotting is the company issuing its capital,
     * so the register must not be able to hand out more of an offering than
     * the offering holds.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $application = $this->application();
                $offeringId = $application->share_offering_id;

                if ($offeringId === null || $validator->errors()->has('shares_allotted')) {
                    return;
                }

                $totalShares = (int) ($application->offering?->total_shares ?? 0);

                // The application's existing allotment is excluded so revising
                // it is measured against what everyone else holds.
                $allottedElsewhere = app(ShareAllotmentRepository::class)
                    ->allottedSharesForOffering($offeringId, $application->id);

                $remaining = max(0, $totalShares - $allottedElsewhere);

                if ((int) $this->input('shares_allotted') > $remaining) {
                    $validator->errors()->add(
                        'shares_allotted',
                        "Only {$remaining} shares remain unallotted in this offering.",
                    );
                }
            },
        ];
    }

    private function application(): ShareApplication
    {
        return $this->route('application');
    }
}
