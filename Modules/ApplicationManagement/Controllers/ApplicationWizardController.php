<?php

namespace Modules\ApplicationManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Services\NepaliAmountWordsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicantManagement\Services\ProfileDocumentService;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Models\ShareApplicationVoucher;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\ApplicationManagement\Requests\StoreDraftStepRequest;
use Modules\ApplicationManagement\Requests\SubmitApplicationRequest;
use Modules\ApplicationManagement\Services\ApplicationWizardService;
use Modules\CompanyManagement\Repositories\ShareOfferingRepository;
use Modules\PaymentManagement\Repositories\PaymentMethodRepository;

class ApplicationWizardController extends Controller
{
    public function __construct(
        private ApplicationWizardService $wizard,
        private ShareApplicationRepository $applications,
        private ProfileRepository $profiles,
    ) {}

    public function index(Request $request, ShareOfferingRepository $offerings)
    {
        $applicantProfile = $this->profiles->findByUserId($request->user()->id);
        $draft = $this->applications->latestDraftForUser($request->user()->id);

        return Inertia::render('Applications/Wizard', [
            'draft' => $draft,
            'activeApplication' => $this->applications->activeForUser($request->user()->id),
            'profile' => $applicantProfile,
            'profileCompleted' => $applicantProfile?->isProfileComplete() ?? false,
            'profileStatus' => $applicantProfile->profile_status ?? ProfileStatus::Incomplete,
            'offerings' => $offerings->openNow(),
            'focalPerson' => $this->focalPersonSummary($draft, $applicantProfile),
        ]);
    }

    /**
     * Who the wizard's focal person field starts out showing.
     *
     * An existing draft is authoritative even when it holds no focal person —
     * that means the applicant cleared the field, and re-seeding the profile
     * default over the top would undo them. Only a first draft inherits it.
     *
     * @return array{code: string, name: string}|null
     */
    private function focalPersonSummary(?ShareApplication $draft, ?Profile $profile): ?array
    {
        $focalPerson = $draft ? $draft->focalPerson : $profile?->focalPerson;

        return $focalPerson ? [
            'code' => $focalPerson->focal_person_code,
            'name' => $focalPerson->name,
        ] : null;
    }

    public function show(
        Request $request,
        ShareApplication $application,
        NepaliAmountWordsService $words,
        PaymentMethodRepository $paymentMethods,
    ) {
        Gate::authorize('view', $application);

        $application->load([
            'applicant.permanentAddress',
            'applicant.temporaryAddress',
            'applicant.nominees',
            'applicant.sourcesOfFunds',
            'applicant.experiences',
            'applicant.documents',
            'offering.company',
            'vouchers',
            'paymentTransactions' => fn ($query) => $query->latest(),
            'paymentTransactions.paymentMethod:id,name,account_name,account_number,bank_name',
        ]);

        $isOwner = $application->applicant?->user_id === $request->user()->id;

        // The company account the money was collected into: the method used on
        // the payment if recorded, otherwise the first active method.
        $collectionAccount = $application->paymentTransactions->first()?->paymentMethod
            ?? $paymentMethods->active(['id', 'name', 'account_name', 'account_number', 'bank_name'])->first();

        return Inertia::render('Applications/Show', [
            'application' => $application,
            'collectionAccount' => $collectionAccount?->only(['name', 'account_name', 'account_number', 'bank_name']),
            'amountInWords' => $words->toWords((string) $application->total_amount_declared),
            'sharesInWords' => str_replace(' Rupaiya Matra', '', $words->toWords($application->shares_applied)),
            // The document route serves the logged-in user's own file, so only
            // offer these to the owner — and only when the file actually exists,
            // so the form doesn't render broken images where a doc is missing.
            'photoUrl' => $this->documentUrl($application, $isOwner, 'photo'),
            'signatureUrl' => $this->documentUrl($application, $isOwner, 'signature'),
            'voucherImageUrls' => $application->vouchers
                ->filter->has_image
                ->map(fn ($voucher) => [
                    'id' => $voucher->id,
                    'transaction_code' => $voucher->transaction_code,
                    'url' => route('applications.voucher-image', [$application->id, $voucher->id]),
                ])
                ->values(),
        ]);
    }

    /**
     * A link to one of the applicant's own uploads, or null when they aren't
     * the owner or never uploaded it.
     */
    private function documentUrl(ShareApplication $application, bool $isOwner, string $slug): ?string
    {
        if (! $isOwner) {
            return null;
        }

        $documentType = ProfileDocumentService::TYPE_BY_SLUG[$slug] ?? null;

        $exists = $application->applicant?->documents
            ->contains(fn ($document) => $document->document_type === $documentType);

        return $exists ? route('profile.documents.show', $slug) : null;
    }

    public function voucherImage(ShareApplication $application, ShareApplicationVoucher $voucher)
    {
        Gate::authorize('view', $application);

        // The voucher id comes from the URL, so confirm it belongs to the
        // application the gate just authorised.
        abort_unless($voucher->share_application_id === $application->id, 404);

        abort_unless(
            $voucher->image_path && Storage::disk('private')->exists($voucher->image_path),
            404,
        );

        return response()->file(Storage::disk('private')->path($voucher->image_path));
    }

    public function storeDraft(StoreDraftStepRequest $request)
    {
        $this->wizard->saveDraft($request->user(), $request->validated('payload'));

        return back()->with('success', 'Draft saved.');
    }

    public function submit(SubmitApplicationRequest $request, ShareApplication $application)
    {
        $application->load('applicant');

        Gate::authorize('submit', $application);

        $this->wizard->submit($request->user(), $application);

        return redirect()->route('applications.wizard')->with('success', 'Application submitted successfully.');
    }
}
