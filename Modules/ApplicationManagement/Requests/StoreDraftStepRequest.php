<?php

namespace Modules\ApplicationManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\ApplicantManagement\Enums\SourceOfFunds;
use Modules\ApplicationManagement\Models\ShareApplicationVoucher;
use Modules\CompanyManagement\Models\ShareOffering;

class StoreDraftStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * An Application Verifier transcribing a paper form routes through
     * {applicant}; the applicant's own draft save has no such route
     * parameter. Paper vouchers are physically in hand when this is filled
     * in, so nothing about them should be left for later the way a
     * self-service applicant's half-finished draft is allowed to be.
     */
    protected function isStaffEntry(): bool
    {
        return $this->route('applicant') !== null;
    }

    public function rules(): array
    {
        $rules = [
            'step' => ['sometimes', 'integer'],
            'payload' => ['required', 'array'],
            'payload.investment_sources' => ['nullable', 'array'],
            'payload.investment_sources.*' => ['string', Rule::enum(SourceOfFunds::class)],
            'payload.investment_source_other_detail' => ['nullable', 'string', 'max:500'],
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
            // The four the receipt actually prints, plus the two channels the
            // applicant most often pays through. Previously an applicant could
            // only declare connect_ips, mobile_banking or cheque, none of
            // which is a box on the receipt except the last, so most receipts
            // printed with nothing ticked.
            'payload.vouchers.*.payment_type' => ['nullable', 'in:cheque,self_cheque_deposit,online_transfer,cash,connect_ips,mobile_banking'],
            'payload.vouchers.*.payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'payload.vouchers.*.deposited_bank' => ['nullable', 'string', 'max:255'],
            'payload.vouchers.*.transaction_code' => ['nullable', 'string', 'max:100'],
            'payload.vouchers.*.asba_reference' => ['nullable', 'string', 'max:100'],
            'payload.vouchers.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payload.vouchers.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'payload.shares_applied' => ['required', 'integer', 'min:1'],
            'payload.declaration_accepted' => ['nullable', 'boolean'],
        ];

        if ($this->isStaffEntry()) {
            // A verifier transcribing a paper form has the slip and the money
            // already accounted for — every field on it is known, so nothing
            // here is left optional the way a self-service draft can be.
            $rules['payload.vouchers'] = ['required', 'array', 'min:1', 'max:20'];

            foreach (array_keys((array) $this->input('payload.vouchers', [])) as $index) {
                $rules["payload.vouchers.{$index}.payment_type"] = ['required', 'in:cheque,self_cheque_deposit,online_transfer,cash,connect_ips,mobile_banking'];
                $rules["payload.vouchers.{$index}.deposited_bank"] = ['required', 'string', 'max:255'];
                $rules["payload.vouchers.{$index}.transaction_code"] = ['required', 'string', 'max:100'];
                $rules["payload.vouchers.{$index}.amount"] = ['required', 'numeric', 'min:0.01'];
                $rules["payload.vouchers.{$index}.payment_date"] = ['required', 'date', 'before_or_equal:today'];
                $rules["payload.vouchers.{$index}.image"] = [
                    $this->voucherAlreadyHasImage($index) ? 'nullable' : 'required',
                    'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                ];
            }
        }

        return $rules;
    }

    /**
     * A row already carrying an uploaded slip doesn't need a fresh one on
     * every re-save — only a brand new row, or one whose slip was removed,
     * still needs the file input filled in.
     */
    private function voucherAlreadyHasImage(int $index): bool
    {
        $id = $this->input("payload.vouchers.{$index}.id");

        if (blank($id)) {
            return false;
        }

        return ShareApplicationVoucher::whereKey($id)->whereNotNull('image_path')->exists();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->isStaffEntry()) {
                return;
            }

            $vouchers = (array) $this->input('payload.vouchers', []);

            if ($vouchers === []) {
                return;
            }

            $offering = ShareOffering::find($this->input('payload.share_offering_id'));

            if (! $offering) {
                return;
            }

            $shares = (int) $this->input('payload.shares_applied', 0);
            $declared = round($shares * (float) $offering->share_rate, 2);
            $stated = round(array_sum(array_map(
                fn ($voucher) => (float) ($voucher['amount'] ?? 0),
                $vouchers,
            )), 2);

            // A whole rupee's slack for rounding across several slips; anything
            // more means a voucher's amount was mistyped or one is missing.
            if (abs($declared - $stated) > 1.0) {
                $validator->errors()->add(
                    'payload.vouchers',
                    "The vouchers total {$stated} but the application declares {$declared} (".
                    "{$shares} shares at {$offering->share_rate} each). Correct the amounts so they add up.",
                );
            }
        });
    }
}
