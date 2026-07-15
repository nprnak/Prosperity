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
            'payload.investment_source' => ['nullable', 'in:salary,dividend,property_sale,house_rent,share_trading,other'],
            'payload.investment_source_other' => ['nullable', 'string', 'max:255'],
            'payload.share_heir_name' => ['nullable', 'string', 'max:255'],
            'payload.share_heir_relation' => ['nullable', 'string', 'max:255'],
            'payload.share_heir_mobile' => ['nullable', 'string', 'max:50'],
            'payload.share_offering_id' => ['required', 'integer', 'exists:share_offerings,id'],
            'payload.asba_reference' => ['nullable', 'string', 'max:100'],
            'payload.shares_applied' => ['required', 'integer', 'min:1'],
            'payload.declaration_accepted' => ['nullable', 'boolean'],
        ];
    }
}
