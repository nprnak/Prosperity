<?php

namespace Modules\ApplicationManagement\Services;

use App\Models\User;
use App\Services\NumberGeneratorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Notifications\ApplicationSubmittedNotification;
use Modules\ApplicationManagement\Repositories\ApplicationEventRepository;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\SettingsManagement\Models\Setting;
use Modules\UserManagement\Services\FocalPersonService;

/**
 * The share-application wizard: draft saving (with offering/profile business
 * rules) and final submission (numbering, status transition, notifications).
 */
class ApplicationWizardService
{
    public function __construct(
        private ShareApplicationRepository $applications,
        private ApplicationEventRepository $events,
        private ProfileRepository $profiles,
        private NumberGeneratorService $numbers,
        private FocalPersonService $focalPersons,
    ) {}

    /**
     * @throws ValidationException
     */
    public function saveDraft(User $user, array $payload): ShareApplication
    {
        $applicant = $this->profiles->findByUserId($user->id);

        if (! $applicant?->isProfileApproved()) {
            throw ValidationException::withMessages([
                'profile' => 'Your profile must be approved before you can apply for shares. Complete it and submit it for review from the Profile page.',
            ]);
        }

        $offering = ShareOffering::query()->with('company')->findOrFail($payload['share_offering_id']);

        if (! $offering->isOpenForApplications()) {
            throw ValidationException::withMessages([
                'payload.share_offering_id' => 'This share offering is not open for applications.',
            ]);
        }

        $shares = (int) $payload['shares_applied'];

        if ($shares < $offering->min_shares || $shares > $offering->max_shares) {
            throw ValidationException::withMessages([
                'payload.shares_applied' => "Shares must be between {$offering->min_shares} and {$offering->max_shares} for this offering.",
            ]);
        }

        $remaining = $offering->sharesRemaining();

        if ($remaining <= 0) {
            throw ValidationException::withMessages([
                'payload.share_offering_id' => 'This share offering is fully subscribed.',
            ]);
        }

        if ($shares > $remaining) {
            throw ValidationException::withMessages([
                'payload.shares_applied' => "Only {$remaining} shares remain in this offering.",
            ]);
        }

        // Resolved before anything is written, so a mistyped code rejects the
        // whole save instead of half-applying it. `false` means the field was
        // not submitted at all, which is different from submitting it empty to
        // clear the credit.
        $focalPerson = array_key_exists('focal_person_code', $payload)
            ? $this->focalPersons->resolveCodeForApplicant(
                $payload['focal_person_code'], $applicant, 'payload.focal_person_code'
            )
            : false;

        // Sources are a set: whatever the applicant ticked replaces what was
        // there, so unticking one actually removes it.
        if (isset($payload['investment_sources'])) {
            $sources = array_values(array_unique((array) $payload['investment_sources']));

            $applicant->sourcesOfFunds()->whereNotIn('source_type', $sources ?: [''])->delete();

            foreach ($sources as $source) {
                $applicant->sourcesOfFunds()->updateOrCreate(['source_type' => $source]);
            }
        }

        if (! empty($payload['share_heir_name'])) {
            $nominee = $applicant->nominees()->first() ?? $applicant->nominees()->make();
            $nominee->fill([
                'full_name' => $payload['share_heir_name'],
                'relationship' => $payload['share_heir_relation'] ?? ($nominee->relationship ?: 'Family'),
                'mobile' => $payload['share_heir_mobile'] ?? $nominee->mobile,
            ])->save();
        }

        // Rate and total are always taken from the offering, never from the client.
        $totalAmount = number_format($shares * (float) $offering->share_rate, 2, '.', '');

        $application = $this->applications->firstOrNewDraft($applicant->id);

        if (! $application->exists) {
            $application->application_number = 'DRAFT-'.str_pad((string) $applicant->id, 6, '0', STR_PAD_LEFT);
            // A new draft starts credited to the applicant's default focal
            // person; the code they type below overrides it. From here on the
            // application owns its own value, so re-assigning the default later
            // leaves this offering's attribution alone.
            $application->focal_person_id = $applicant->focal_person_id;
        }

        $attributes = [
            'share_offering_id' => $offering->id,
            'issue_code' => $offering->company->code.'-'.$offering->fiscal_year,
            'shares_applied' => $shares,
            'amount_per_share' => $offering->share_rate,
            'total_amount_declared' => $totalAmount,
            'declaration_accepted' => (bool) ($payload['declaration_accepted'] ?? false),
        ];

        if ($focalPerson !== false) {
            $attributes['focal_person_id'] = $focalPerson?->id;
        }

        $application->fill($attributes);

        $application->save();

        if (isset($payload['vouchers'])) {
            $this->syncVouchers($application, (array) $payload['vouchers'], $applicant->id);
        }

        return $application;
    }

    /**
     * Replaces the application's voucher rows with what the form submitted.
     * Rows carrying an id keep their existing slip unless a new file is
     * attached; rows the applicant removed are deleted along with their file.
     */
    private function syncVouchers(ShareApplication $application, array $rows, int $applicantId): void
    {
        $existing = $application->vouchers()->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $row) {
            $voucher = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            $voucher ??= $application->vouchers()->make();

            $voucher->fill([
                'payment_type' => $row['payment_type'] ?? null,
                'deposited_bank' => $row['deposited_bank'] ?? null,
                'transaction_code' => $row['transaction_code'] ?? null,
                'asba_reference' => $row['asba_reference'] ?? null,
                'amount' => ($row['amount'] ?? null) === '' ? null : ($row['amount'] ?? null),
            ]);

            if (($row['image'] ?? null) instanceof UploadedFile) {
                $voucher->deleteImage();
                $voucher->image_path = $row['image']->store('applications/'.$applicantId, 'private');
            }

            $application->vouchers()->save($voucher);
            $kept[] = $voucher->id;
        }

        foreach ($existing as $voucher) {
            if (! in_array($voucher->id, $kept, true)) {
                $voucher->deleteImage();
                $voucher->delete();
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public function submit(User $user, ShareApplication $application): ShareApplication
    {
        $application->load('applicant');

        if (! $application->applicant?->isProfileApproved()) {
            $this->failSubmission('Your profile must be approved before submitting an application. Submit it for review from the Profile page.');
        }

        if ($application->share_offering_id && ! $application->offering?->isOpenForApplications()) {
            $this->failSubmission('This share offering is no longer open for applications.');
        }

        // Shares may have been taken by other applicants since the draft was saved.
        $remaining = $application->offering?->sharesRemaining();

        if ($remaining !== null && $application->shares_applied > $remaining) {
            $this->failSubmission(
                $remaining > 0
                    ? "Only {$remaining} shares remain in this offering. Reduce your applied shares and save the draft again."
                    : 'This share offering is now fully subscribed.',
            );
        }

        $maxApplications = (int) Setting::get('max_applications_per_user', 5);

        if ($this->applications->activeCountForApplicant($application->applicant_id) >= $maxApplications) {
            $this->failSubmission("You have reached the maximum of {$maxApplications} active applications.");
        }

        // A code with no slip, or a slip with no code, can't be verified by
        // finance — so the pairing is enforced at submission rather than while
        // the applicant is still filling the draft in.
        $vouchers = $application->vouchers()->get();

        if ($vouchers->isEmpty()) {
            $this->failSubmission('Add at least one bank voucher with its transaction code before submitting.');
        }

        if ($vouchers->contains(fn ($voucher) => blank($voucher->transaction_code) || ! $voucher->has_image)) {
            $this->failSubmission('Every bank voucher needs both a transaction code and an uploaded slip.');
        }

        if (str_starts_with($application->application_number, 'DRAFT-')) {
            $application->application_number = $this->numbers->generateApplicationNumber();
        }

        $fromStatus = $application->status;
        $application->status = ApplicationStatus::Submitted;
        $application->submitted_at = now();
        $application->save();

        $this->events->record($application, $user->id, $fromStatus, ApplicationStatus::Submitted, 'Application submitted by applicant.');

        // The declared payment goes straight into finance's verification queue
        // as a pending transaction — no separate "record payment" step needed.
        // One pending transaction per declared deposit, so finance verifies each
        // slip on its own rather than one lump sum.
        if (! $application->paymentTransactions()->exists()) {
            $amounts = $this->allocateAmounts($application, $vouchers);

            foreach ($vouchers as $index => $voucher) {
                $isCheque = $voucher->payment_type === 'cheque';

                $application->paymentTransactions()->create([
                    'receipt_number' => $this->numbers->generateReceiptNumber(),
                    'amount' => $amounts[$index],
                    'payment_mode' => ['connect_ips' => 'ips', 'mobile_banking' => 'mobile_banking', 'cheque' => 'cheque'][$voucher->payment_type] ?? 'online_transfer',
                    'bank_name' => $voucher->deposited_bank,
                    'payment_reference_no' => $isCheque ? null : $voucher->transaction_code,
                    'cheque_no' => $isCheque ? $voucher->transaction_code : null,
                    'payment_date' => now()->toDateString(),
                    'verification_status' => 'pending',
                    'issued_by' => $user->id,
                ]);
            }
        }

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationSubmittedNotification($application));
        }

        $application->applicant?->user?->notify(new ApplicationSubmittedNotification($application));

        return $application;
    }

    /**
     * The amount to record against each voucher, indexed like $vouchers.
     *
     * Amounts are optional on the form, so anything left blank splits whatever
     * the declared total still has unaccounted for. Working in paisa keeps the
     * split exact — the first rows absorb the remainder rather than the total
     * drifting by a paisa per voucher.
     *
     * @return array<int, string>
     */
    private function allocateAmounts(ShareApplication $application, $vouchers): array
    {
        $declared = $this->toPaisa((string) $application->total_amount_declared);
        $stated = 0;
        $blank = [];

        foreach ($vouchers as $index => $voucher) {
            if ($voucher->amount === null) {
                $blank[] = $index;
            } else {
                $stated += $this->toPaisa((string) $voucher->amount);
            }
        }

        $amounts = [];

        foreach ($vouchers as $index => $voucher) {
            if ($voucher->amount !== null) {
                $amounts[$index] = number_format((float) $voucher->amount, 2, '.', '');
            }
        }

        if ($blank === []) {
            return $amounts;
        }

        $remainder = max(0, $declared - $stated);
        $share = intdiv($remainder, count($blank));
        $extra = $remainder % count($blank);

        foreach ($blank as $position => $index) {
            $paisa = $share + ($position < $extra ? 1 : 0);
            $amounts[$index] = number_format($paisa / 100, 2, '.', '');
        }

        return $amounts;
    }

    private function toPaisa(string $amount): int
    {
        $normalized = preg_replace('/[^0-9.]/', '', $amount) ?: '0';
        [$rupees, $paisa] = array_pad(explode('.', $normalized, 2), 2, '0');
        $paisa = str_pad(substr($paisa, 0, 2), 2, '0');

        return ((int) $rupees * 100) + (int) $paisa;
    }

    /**
     * @throws ValidationException
     */
    private function failSubmission(string $message): never
    {
        throw ValidationException::withMessages(['profile' => $message])
            ->redirectTo(route('applications.wizard'));
    }
}
