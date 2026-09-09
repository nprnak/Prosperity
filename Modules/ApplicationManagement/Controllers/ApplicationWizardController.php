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
use Modules\ApplicationManagement\Enums\ApplicationStatus;
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
        // A returned application is editable too, so the wizard opens on it
        // rather than on an empty form the applicant cannot correct anything with.
        $draft = $this->applications->latestEditableForUser($request->user()->id);

        return Inertia::render('Applications/Wizard', [
            'draft' => $draft,
            // Only applications still with staff lock the form, and only for
            // their own offering.
            'activeApplications' => $this->applications->inFlightForUser($request->user()->id),
            // Passed explicitly: latest_workflow_remarks is an accessor, not an
            // appended attribute, so it never reaches the page on its own.
            'returnedReason' => $draft?->status === ApplicationStatus::Returned
                ? $draft->latest_workflow_remarks
                : null,
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
            'focalPerson:id,name',
            'vouchers',
            'paymentTransactions' => fn ($query) => $query->latest(),
            'paymentTransactions.voucher:id,payment_transaction_id,voucher_number',
            'paymentTransactions.paymentMethod:id,name,account_name,account_number,bank_name',
        ]);

        $isOwner = $application->applicant?->user_id === $request->user()->id;

        // The company account the money was collected into: the method used on
        // the payment if recorded, otherwise the first active method.
        $collectionAccount = $application->paymentTransactions->first()?->paymentMethod
            ?? $paymentMethods->active(['id', 'name', 'account_name', 'account_number', 'bank_name'])->first();

        $payment = $application->paymentTransactions->first();
        $receiptVoucher = $payment?->voucher;

        return Inertia::render('Applications/Show', [
            'application' => $application,
            'collectionAccount' => $collectionAccount?->only(['name', 'account_name', 'account_number', 'bank_name']),
            'amountInWords' => $words->toWords((string) $application->total_amount_declared),
            'sharesInWords' => str_replace(' Rupaiya Matra', '', $words->toWords($application->shares_applied)),
            // Served through this page's own gated routes rather than the
            // self-service /profile/documents/{type} one, which only ever
            // serves the *logged-in* user's own files — wrong for the staff
            // member who filed this on someone else's behalf, and previously
            // meant photo/signature only ever showed for the applicant.
            'photoUrl' => $this->documentUrl($application, 'photo'),
            'signatureUrl' => $this->documentUrl($application, 'signature'),
            'voucherImageUrls' => $application->vouchers
                ->filter->has_image
                ->map(fn ($voucher) => [
                    'id' => $voucher->id,
                    'transaction_code' => $voucher->transaction_code,
                    'url' => route('applications.voucher-image', [$application->id, $voucher->id]),
                ])
                ->values(),
            'receipt' => $receiptVoucher ? [
                'voucherNumber' => $receiptVoucher->voucher_number,
                'receiptNumber' => $payment?->receipt_number,
                'showUrl' => route('vouchers.show', $receiptVoucher),
                'downloadUrl' => route('vouchers.download', $receiptVoucher),
            ] : null,
            // The applicant's own wizard is meaningless — and permission-gated
            // shut — for a staff member previewing someone else's application;
            // send them back to the admin detail page instead.
            'backRoute' => $isOwner
                ? route('applications.wizard')
                : route('admin.applications.show', $application->id),
            'backLabel' => $isOwner ? null : 'Back to Applications',
        ]);
    }

    /**
     * A link to the applicant's uploaded document, for anyone the "view"
     * gate already let onto this page — owner or staff alike. Null only
     * when the file was never uploaded, so the form never renders a broken
     * image link.
     */
    private function documentUrl(ShareApplication $application, string $slug): ?string
    {
        $documentType = ProfileDocumentService::TYPE_BY_SLUG[$slug] ?? null;

        $exists = $application->applicant?->documents
            ->contains(fn ($document) => $document->document_type === $documentType);

        return $exists ? route('applications.'.$slug, $application->id) : null;
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

    public function photo(ShareApplication $application)
    {
        return $this->applicantDocument($application, 'photo');
    }

    public function signature(ShareApplication $application)
    {
        return $this->applicantDocument($application, 'signature');
    }

    /**
     * Serve one of the applicant's own documents for this application, to
     * anyone the "view" gate lets onto the application — the owner or a
     * staff member who filed it on their behalf alike.
     */
    private function applicantDocument(ShareApplication $application, string $slug)
    {
        Gate::authorize('view', $application);

        $documentType = ProfileDocumentService::TYPE_BY_SLUG[$slug] ?? null;

        $document = $application->applicant?->documents
            ->firstWhere('document_type', $documentType);

        abort_unless(
            $document && Storage::disk('private')->exists($document->file_path),
            404,
        );

        return response()->file(Storage::disk('private')->path($document->file_path));
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
