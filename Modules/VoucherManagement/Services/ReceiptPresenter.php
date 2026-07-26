<?php

namespace Modules\VoucherManagement\Services;

use App\Services\NepaliAmountWordsService;
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

        $application?->loadMissing(['applicant', 'offering.company']);
        $payment?->loadMissing('deposits');

        $company = $application?->offering?->company;
        $deposits = $payment?->deposits ?? collect();

        return [
            'application' => $application,
            'payment' => $payment,
            'voucher' => $voucher,
            'deposits' => $deposits,
            'companyName' => $company?->name ?? Setting::get('org_name', 'Prosperity Holdings Limited'),
            'companyAddress' => $company?->address ?? Setting::get('org_address'),
            'logoDataUri' => $this->logoDataUri($company?->logo_path),
            'amountInEnglishWords' => $this->words->toEnglishWords($payment?->amount ?? 0),
            'printedModes' => self::PRINTED_MODES,
            'tickedMode' => $this->tickedMode($payment?->payment_mode),
            'holdingIdLabel' => self::ID_TYPE_LABELS[$payment?->id_type] ?? null,
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

        return 'data:'.Storage::disk('private')->mimeType($path)
            .';base64,'.base64_encode(Storage::disk('private')->get($path));
    }
}
