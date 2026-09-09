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
    /**
     * Statuses an applicant may submit from: a draft they are still filling
     * in, and one handed back to them for correction.
     */
    private const SUBMITTABLE_STATUSES = [
        ApplicationStatus::Draft,
        ApplicationStatus::Returned,
    ];

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
    public function saveDraft(User $user, array $payload, ?int $enteredBy = null): ShareApplication
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
                $applicant->sourcesOfFunds()->updateOrCreate(
                    ['source_type' => $source],
                    // Only "other" carries free text; every other row's description
                    // is cleared so an unticked-then-reticked box doesn't keep stale text.
                    ['description' => $source === 'other' ? ($payload['investment_source_other_detail'] ?? null) : null],
                );
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

        $application = $this->applications->firstOrNewEditable($applicant->id);

        if (! $application->exists) {
            $application->application_number = 'DRAFT-'.str_pad((string) $applicant->id, 6, '0', STR_PAD_LEFT);
            // A new draft starts credited to the applicant's default focal
            // person; the code they type below overrides it. From here on the
            // application owns its own value, so re-assigning the default later
            // leaves this offering's attribution alone.
            $application->focal_person_id = $applicant->focal_person_id;

            // Recorded once, at creation, so an Application Verifier can find
            // "applications I filed" — null for the normal self-service path.
            if ($enteredBy) {
                $application->entered_by = $enteredBy;
            }
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

            // Falling back to the transaction code keeps a client that has
            // lost track of its row ids from destroying the slips: deleting an
            // uploaded file is irreversible, and the form already requires a
            // distinct code per deposit, so it identifies the row just as well.
            if (! $voucher && filled($row['transaction_code'] ?? null)) {
                $voucher = $existing->first(fn ($candidate) => $candidate->transaction_code === $row['transaction_code']
                    && ! in_array($candidate->id, $kept, true));
            }

            $voucher ??= $application->vouchers()->make();

            $voucher->fill([
                'payment_type' => $row['payment_type'] ?? null,
                'deposited_bank' => $row['deposited_bank'] ?? null,
                'transaction_code' => $row['transaction_code'] ?? null,
                'asba_reference' => $row['asba_reference'] ?? null,
                'amount' => ($row['amount'] ?? null) === '' ? null : ($row['amount'] ?? null),
                // The day the money actually moved, which the receipt prints.
                'payment_date' => ($row['payment_date'] ?? null) === '' ? null : ($row['payment_date'] ?? null),
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

        // Only an application still in the applicant's hands may be submitted.
        // Without this, re-posting the route on an application that has already
        // been through the chain resets it to Submitted while its receipt and
        // its sign-offs stay in place.
        if (! in_array($application->status, self::SUBMITTABLE_STATUSES, true)) {
            $this->failSubmission(
                'This application has already been submitted and is with the review team.'
            );
        }

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
            $companyCode = $application->offering?->company?->code ?? 'PHL';
            $application->application_number = $this->numbers->generateApplicationNumber($companyCode);
        }

        $fromStatus = $application->status;

        // An application coming back after a return starts a fresh cycle, so
        // the sign-offs given to the version that was sent back no longer
        // count toward the act-once rule and three people must sign the
        // corrected one. Mirrors what the KYC profile already does.
        if ($fromStatus === ApplicationStatus::Returned) {
            $application->restartWorkflowCycle();
        }

        $application->status = ApplicationStatus::Submitted;
        $application->submitted_at = now();
        $application->save();

        $this->events->record($application, $user->id, $fromStatus, ApplicationStatus::Submitted, 'Application submitted by applicant.');

        // The declared payment goes straight into finance's verification queue
        // — no separate "record payment" step needed. One pending transaction,
        // because one application earns one receipt, with a deposit row per
        // declared slip so finance still verifies each on its own.
        //
        // No receipt number is claimed here: it is issued with the receipt
        // itself. Taking one per deposit at submission burned numbers on
        // applications that were never approved.
        if (! $application->paymentTransactions()->exists()) {
            $amounts = $this->allocateAmounts($application, $vouchers);

            $transaction = $application->paymentTransactions()->create([
                'amount' => $this->sumAmounts($amounts),
                'payment_mode' => $this->receiptPaymentMode($vouchers->first()?->payment_type),
                'verification_status' => 'pending',
                'holding_id_no' => $application->applicant?->boid,
                'id_type' => $application->applicant?->boid ? 'boid' : null,
                'issued_by' => $user->id,
            ]);

            foreach ($vouchers as $index => $voucher) {
                $isCheque = $voucher->payment_type === 'cheque';

                $transaction->deposits()->create([
                    'share_application_voucher_id' => $voucher->id,
                    'bank_name' => $voucher->deposited_bank,
                    'reference_no' => $isCheque ? null : $voucher->transaction_code,
                    'cheque_no' => $isCheque ? $voucher->transaction_code : null,
                    'amount' => $amounts[$index],
                    'payment_date' => $voucher->payment_date,
                    'verification_status' => 'pending',
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
    /**
     * The mode the receipt ticks a box for.
     *
     * The paper receipt has one checkbox row, so a receipt carries one mode
     * even when its deposits arrived by different routes — finance overrides
     * it in that case. IPS and mobile banking are not boxes on the form; both
     * are an online transfer as far as the receipt is concerned.
     */
    private function receiptPaymentMode(?string $declaredType): string
    {
        return match ($declaredType) {
            'cheque' => 'cheque',
            'self_cheque_deposit' => 'self_cheque_deposit',
            'cash' => 'cash',
            default => 'online_transfer',
        };
    }

    /** @param  array<int, string>  $amounts */
    private function sumAmounts(array $amounts): string
    {
        $paisa = array_sum(array_map(fn (string $amount) => $this->toPaisa($amount), $amounts));

        return number_format($paisa / 100, 2, '.', '');
    }

    private function failSubmission(string $message): never
    {
        throw ValidationException::withMessages(['profile' => $message])
            ->redirectTo(route('applications.wizard'));
    }
}
