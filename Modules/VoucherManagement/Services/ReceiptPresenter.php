<?php

namespace Modules\VoucherManagement\Services;

use App\Models\User;
use App\Services\NepaliAmountWordsService;
use Modules\ApplicantManagement\Models\Profile;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentManagement\Models\PaymentDeposit;
use Modules\SettingsManagement\Models\Setting;
use Modules\VoucherManagement\Models\Voucher;

/**
 * Everything printed on a payment receipt, assembled in one place.
 *
 * The PDF and any on-screen rendering read from here, so the two cannot drift
 * on what a receipt says.
 */
class ReceiptPresenter
{
    /**
     * The four boxes the printed receipt book actually has.
     *
     * IPS and mobile banking are not among them; both are an online transfer
     * as far as the paper form is concerned, and printing extra boxes would
     * make the generated receipt disagree with the book it continues.
     */
    public const PRINTED_MODES = [
        'cheque' => 'Cheque',
        'self_cheque_deposit' => 'Self Cheque Deposit',
        'online_transfer' => 'Online Transfer',
        'cash' => 'Cash',
    ];

    private const MODE_ALIASES = [
        'ips' => 'online_transfer',
        'mobile_banking' => 'online_transfer',
        'connect_ips' => 'online_transfer',
    ];

    private const ID_TYPE_LABELS = [
        'boid' => 'BOID',
        'citizenship' => 'Citizenship',
        'national_id' => 'National ID',
        'pan' => 'PAN',
    ];

    public function __construct(
        private NepaliAmountWordsService $words,
        private VoucherQrService $qr,
    ) {}

    /** @return array<string, mixed> */
    public function data(Voucher $voucher): array
    {
        $payment = $voucher->paymentTransaction;
        $application = $payment?->shareApplication;

        $configuredVerifier = $this->configuredSigner('receipt_verifier_user_id');
        $configuredReviewer = $this->configuredSigner('receipt_reviewer_user_id');
        $configuredApprover = $this->configuredSigner('receipt_approver_user_id');

        $verifierUser = $configuredVerifier ?? $application?->verifier;
        $reviewerUser = $configuredReviewer ?? $application?->reviewer;
        $approverUser = $configuredApprover ?? $application?->approver;

        $application?->loadMissing(['applicant', 'offering.company', 'verifier:id,name', 'reviewer:id,name', 'approver:id,name']);
        $payment?->loadMissing('deposits');

        $company = $application?->offering?->company;
        $deposits = $payment?->deposits ?? collect();

        return [
            'application' => $application,
            'payment' => $payment,
            'voucher' => $voucher,
            'deposits' => $deposits,
            'sharesApplied' => $application?->shares_applied,
            'companyName' => $company?->name ?? Setting::get('org_name', 'Prosperity Holdings Limited'),
            'companyAddress' => $company?->address ?? Setting::get('org_address'),
            'logoDataUri' => $this->logoDataUri($company?->logo_path),
            'companyStampDataUri' => $this->settingImageDataUri((string) Setting::get('org_stamp', ''))
                ?? $this->settingImageDataUri((string) Setting::get('org_logo', '')),
            'amountInEnglishWords' => $this->words->toEnglishWords($payment?->amount ?? 0),
            'printedModes' => self::PRINTED_MODES,
            'tickedMode' => $this->tickedMode($payment?->payment_mode),
            'holdingIdLabel' => self::ID_TYPE_LABELS[$payment?->id_type] ?? null,
            'stageSignatures' => [
                'verifier' => [
                    'name' => $verifierUser?->signature_name ?: $verifierUser?->name,
                    'designation' => $verifierUser?->signature_designation,
                    'signedAt' => optional($application?->verified_at)?->format('j M Y, g:i A'),
                    'signatureDataUri' => $this->signatureDataUriForActor($verifierUser),
                ],
                'reviewer' => [
                    'name' => $reviewerUser?->signature_name ?: $reviewerUser?->name,
                    'designation' => $reviewerUser?->signature_designation,
                    'signedAt' => optional($application?->reviewed_at)?->format('j M Y, g:i A'),
                    'signatureDataUri' => $this->signatureDataUriForActor($reviewerUser),
                ],
                'approver' => [
                    'name' => $approverUser?->signature_name ?: $approverUser?->name,
                    'designation' => $approverUser?->signature_designation,
                    'signedAt' => optional($application?->approved_at)?->format('j M Y, g:i A'),
                    'signatureDataUri' => $this->signatureDataUriForActor($approverUser),
                ],
            ],
            'referenceLine' => $this->referenceLine($deposits),
            'paymentDateLine' => $this->paymentDateLine($deposits),
            'verificationUrl' => $this->qr->verificationUrl($voucher),
            'verificationQr' => $this->qr->qrDataUri($voucher),
        ];
    }

    /**
     * "91723543 & 91723546 (10L & 5L each)" — every reference on the receipt,
     * with the amounts spelled out beside them when there is more than one, so
     * the total can be read back against its parts.
     */
    public function referenceLine($deposits): string
    {
        $references = $deposits
            ->map(fn (PaymentDeposit $deposit) => $deposit->reference())
            ->filter()
            ->values();

        if ($references->isEmpty()) {
            return '';
        }

        $line = $references->implode(' & ');

        if ($references->count() < 2) {
            return $line;
        }

        $amounts = $deposits
            ->filter(fn (PaymentDeposit $deposit) => filled($deposit->reference()))
            ->map(fn (PaymentDeposit $deposit) => $this->words->toShorthand($deposit->amount))
            ->implode(' & ');

        return $line.' ('.$amounts.' each)';
    }

    /** "23 June, 2026 & 3 July, 2026" — every date the money arrived on. */
    public function paymentDateLine($deposits): string
    {
        return $deposits
            ->map(fn (PaymentDeposit $deposit) => $deposit->payment_date?->format('j F, Y'))
            ->filter()
            ->unique()
            ->implode(' & ');
    }

    /** The mode whose box is ticked, mapped onto the four the form prints. */
    public function tickedMode(?string $mode): ?string
    {
        $mode = self::MODE_ALIASES[$mode] ?? $mode;

        return array_key_exists((string) $mode, self::PRINTED_MODES) ? $mode : null;
    }

    private function logoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('private')->exists($path)) {
            return null;
        }

        return $this->toDataUri(
            (string) Storage::disk('private')->mimeType($path),
            Storage::disk('private')->get($path),
        );
    }

    private function signatureDataUriForActor(?User $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        if (is_string($actor->signature_path) && $actor->signature_path !== '' && Storage::disk('private')->exists($actor->signature_path)) {
            return $this->toDataUri(
                (string) Storage::disk('private')->mimeType($actor->signature_path),
                Storage::disk('private')->get($actor->signature_path),
            );
        }

        $signaturePath = Profile::query()
            ->where('user_id', $actor->id)
            ->first()?->documents()
            ->where('document_type', 'signature')
            ->value('file_path');

        if (! is_string($signaturePath) || $signaturePath === '' || ! Storage::disk('private')->exists($signaturePath)) {
            return null;
        }

        return $this->toDataUri(
            (string) Storage::disk('private')->mimeType($signaturePath),
            Storage::disk('private')->get($signaturePath),
        );
    }

    private function settingImageDataUri(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '/storage/')) {
            $publicRelativePath = ltrim(substr($value, strlen('/storage/')), '/');

            if (! Storage::disk('public')->exists($publicRelativePath)) {
                return null;
            }

            return $this->toDataUri(
                (string) Storage::disk('public')->mimeType($publicRelativePath),
                Storage::disk('public')->get($publicRelativePath),
            );
        }

        if (! Storage::disk('public')->exists($value)) {
            return null;
        }

        return $this->toDataUri(
            (string) Storage::disk('public')->mimeType($value),
            Storage::disk('public')->get($value),
        );
    }

    private function configuredSigner(string $settingKey): ?User
    {
        $id = (int) Setting::get($settingKey, 0);

        if ($id <= 0) {
            return null;
        }

        return User::query()->find($id);
    }

    private function toDataUri(string $mimeType, string $bytes): string
    {
        return 'data:'.$mimeType.';base64,'.base64_encode($bytes);
    }
}
