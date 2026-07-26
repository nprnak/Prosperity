<?php

namespace Modules\ApplicationManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDraftStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'step' => ['sometimes', 'integer'],
            'payload' => ['required', 'array'],
            'payload.investment_sources' => ['nullable', 'array'],
            'payload.investment_sources.*' => ['string', 'in:salary,dividend,property_sale,house_rent,share_trading'],
            'payload.share_heir_name' => ['nullable', 'string', 'max:255'],
            'payload.share_heir_relation' => ['nullable', 'string', 'max:255'],
            'payload.share_heir_mobile' => ['nullable', 'string', 'max:50'],
            'payload.share_offering_id' => ['required', 'integer', 'exists:share_offerings,id'],
            // A code, not an id — eligibility and self-referral are settled by
            // FocalPersonService when the draft is saved.
            'payload.focal_person_code' => ['nullable', 'string', 'max:20'],
            // Kept lenient so a half-filled draft still saves; the code/slip
            // pairing is enforced at submission instead.
            'payload.vouchers' => ['nullable', 'array', 'max:20'],
            'payload.vouchers.*.id' => ['nullable', 'integer'],
            'payload.vouchers.*.payment_type' => ['nullable', 'in:connect_ips,mobile_banking,cheque'],
            'payload.vouchers.*.deposited_bank' => ['nullable', 'string', 'max:255'],
            'payload.vouchers.*.transaction_code' => ['nullable', 'string', 'max:100'],
            'payload.vouchers.*.asba_reference' => ['nullable', 'string', 'max:100'],
            'payload.vouchers.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payload.vouchers.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'payload.shares_applied' => ['required', 'integer', 'min:1'],
            'payload.declaration_accepted' => ['nullable', 'boolean'],
        ];
    }
}
