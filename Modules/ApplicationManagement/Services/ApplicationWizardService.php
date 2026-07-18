<?php

namespace Modules\ApplicationManagement\Services;

use App\Models\User;
use App\Services\NumberGeneratorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Notifications\ApplicationSubmittedNotification;
use Modules\ApplicationManagement\Repositories\ApplicationEventRepository;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\SettingsManagement\Models\Setting;

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
    ) {
    }

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

        if (! empty($payload['investment_source'])) {
            $applicant->sourcesOfFunds()->updateOrCreate(
                ['source_type' => $payload['investment_source']],
                ['description' => $payload['investment_source_other'] ?? null],
            );
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
        }

        $application->fill([
            'share_offering_id' => $offering->id,
            'issue_code' => $offering->company->code.'-'.$offering->fiscal_year,
            'shares_applied' => $shares,
            'amount_per_share' => $offering->share_rate,
            'total_amount_declared' => $totalAmount,
            'asba_reference' => $payload['asba_reference'] ?? $application->asba_reference,
        ]);

        if (($payload['bank_voucher_image'] ?? null) instanceof UploadedFile) {
            if ($application->bank_voucher_image) {
                Storage::disk('private')->delete($application->bank_voucher_image);
            }

            $application->bank_voucher_image = $payload['bank_voucher_image']
                ->store('applications/'.$applicant->id, 'private');
        }

        $application->save();

        return $application;
    }

    /**
     * @throws ValidationException
     */
    public function submit(User $user, ShareApplication $application, ?string $asbaReference = null): ShareApplication
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

        if (str_starts_with($application->application_number, 'DRAFT-')) {
            $application->application_number = $this->numbers->generateApplicationNumber();
        }

        if (filled($asbaReference)) {
            $application->asba_reference = $asbaReference;
        }

        $fromStatus = $application->status;
        $application->status = ShareApplication::STATUS_SUBMITTED;
        $application->submitted_at = now();
        $application->save();

        $this->events->record($application, $user->id, $fromStatus, ShareApplication::STATUS_SUBMITTED, 'Application submitted by applicant.');

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationSubmittedNotification($application));
        }

        $application->applicant?->user?->notify(new ApplicationSubmittedNotification($application));

        return $application;
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
