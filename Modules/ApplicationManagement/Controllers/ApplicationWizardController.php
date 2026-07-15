<?php

namespace Modules\ApplicationManagement\Controllers;

use App\Http\Controllers\Controller;
use Modules\ApplicationManagement\Requests\StoreDraftStepRequest;
use Modules\ApplicationManagement\Requests\SubmitApplicationRequest;
use Modules\ApplicationManagement\Models\ApplicationEvent;
use Modules\ApplicantManagement\Models\Applicant;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Notifications\ApplicationSubmittedNotification;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ApplicationWizardController extends Controller
{
    public function index(Request $request)
    {
        $applicantProfile = Applicant::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $draft = ShareApplication::query()
            ->whereHas('applicant', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', ShareApplication::STATUS_DRAFT)
            ->with('applicant')
            ->latest()
            ->first();

        $applications = ShareApplication::query()
            ->whereHas('applicant', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest()
            ->get();

        return Inertia::render('Applications/Wizard', [
            'draft' => $draft,
            'applications' => $applications,
            'profile' => $applicantProfile,
            'profileCompleted' => $this->isApplicantProfileComplete($applicantProfile),
        ]);
    }

    public function storeDraft(StoreDraftStepRequest $request)
    {
        $user = $request->user();
        $payload = $request->validated('payload');
        $existingProfile = Applicant::query()->where('user_id', $user->id)->first();

        if (! $this->isApplicantProfileComplete($existingProfile)) {
            return back()->withErrors([
                'profile' => 'Complete your profile first. Only then you can apply for shares from this account.',
            ]);
        }

        $applicant = $existingProfile;

        $applicant->fill([
            'investment_source' => $payload['investment_source'] ?? $applicant->investment_source,
            'investment_source_other' => $payload['investment_source_other'] ?? $applicant->investment_source_other,
            'share_heir_name' => $payload['share_heir_name'] ?? $applicant->share_heir_name,
            'share_heir_relation' => $payload['share_heir_relation'] ?? $applicant->share_heir_relation,
            'share_heir_mobile' => $payload['share_heir_mobile'] ?? $applicant->share_heir_mobile,
        ]);
        $applicant->save();

        $application = ShareApplication::firstOrCreate(
            [
                'applicant_id' => $applicant->id,
                'status' => ShareApplication::STATUS_DRAFT,
            ],
            [
                'application_number' => 'DRAFT-'.str_pad((string) $applicant->id, 6, '0', STR_PAD_LEFT),
                'issue_code' => $payload['issue_code'] ?? null,
                'shares_applied' => $payload['shares_applied'] ?? 1,
                'amount_per_share' => $payload['amount_per_share'] ?? '100.00',
                'total_amount_declared' => $payload['total_amount_declared'] ?? (($payload['shares_applied'] ?? 1) * ($payload['amount_per_share'] ?? 100)),
                'asba_reference' => $payload['asba_reference'] ?? null,
            ]
        );

        $application->fill([
            'shares_applied' => $payload['shares_applied'] ?? $application->shares_applied,
            'amount_per_share' => $payload['amount_per_share'] ?? $application->amount_per_share,
            'total_amount_declared' => $payload['total_amount_declared'] ?? (($payload['shares_applied'] ?? $application->shares_applied) * ($payload['amount_per_share'] ?? $application->amount_per_share)),
            'issue_code' => $payload['issue_code'] ?? $application->issue_code,
            'asba_reference' => $payload['asba_reference'] ?? $application->asba_reference,
        ]);
        $application->save();

        return back()->with('success', 'Draft saved.');
    }

    public function submit(SubmitApplicationRequest $request, ShareApplication $application, NumberGeneratorService $numberGenerator)
    {
        $application->load('applicant');

        abort_unless($application->applicant?->user_id === $request->user()->id, 403);

        if (! $this->isApplicantProfileComplete($application->applicant)) {
            return redirect()->route('applications.wizard')->withErrors([
                'profile' => 'Please complete your profile before submitting the application.',
            ]);
        }

        if (str_starts_with($application->application_number, 'DRAFT-')) {
            $application->application_number = $numberGenerator->generateApplicationNumber();
        }

        if ($request->filled('asba_reference')) {
            $application->asba_reference = $request->validated('asba_reference');
        }

        $fromStatus = $application->status;
        $application->status = ShareApplication::STATUS_SUBMITTED;
        $application->submitted_at = now();
        $application->save();
        $this->logStatusEvent($application, $request->user()->id, $fromStatus, ShareApplication::STATUS_SUBMITTED, 'Application submitted by applicant.');

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationSubmittedNotification($application));
        }

        return redirect()->route('applications.wizard')->with('success', 'Application submitted successfully.');
    }

    private function isApplicantProfileComplete(?Applicant $applicant): bool
    {
        if (! $applicant) {
            return false;
        }

        $requiredFields = [
            'full_name_nepali',
            'full_name_english',
            'date_of_birth',
            'age',
            'father_name',
            'grandfather_name',
            'education',
            'permanent_district',
            'permanent_municipality',
            'permanent_ward',
            'mobile_number',
            'photo_path',
            'citizenship_doc_path',
            'national_id_doc_path',
            'pan_doc_path',
            'boid',
            'crn_number',
            'bank_name',
            'bank_branch',
            'bank_account_number',
            'account_holder_name',
            'asba_consent',
        ];

        foreach ($requiredFields as $field) {
            $value = $applicant->{$field};

            if (blank($value)) {
                return false;
            }

            if (is_string($value) && in_array(trim($value), ['-', 'N/A'], true)) {
                return false;
            }
        }

        return true;
    }

    private function logStatusEvent(ShareApplication $application, ?int $actorId, ?string $fromStatus, string $toStatus, string $remarks): void
    {
        ApplicationEvent::query()->create([
            'share_application_id' => $application->id,
            'actor_id' => $actorId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'remarks' => $remarks,
        ]);
    }
}
