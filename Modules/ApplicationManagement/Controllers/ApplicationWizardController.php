<?php

namespace Modules\ApplicationManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicationManagement\Models\ShareApplication;
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
    ) {
    }

    public function index(Request $request, ShareOfferingRepository $offerings, PaymentMethodRepository $paymentMethods)
    {
        $applicantProfile = $this->profiles->findByUserId($request->user()->id);

        return Inertia::render('Applications/Wizard', [
            'draft' => $this->applications->latestDraftForUser($request->user()->id),
            'applications' => $this->applications->listForUser($request->user()->id),
            'profile' => $applicantProfile,
            'profileCompleted' => $applicantProfile?->isProfileComplete() ?? false,
            'profileStatus' => $applicantProfile->profile_status ?? Profile::PROFILE_INCOMPLETE,
            'offerings' => $offerings->openNow(),
            'paymentMethods' => $paymentMethods->active(['id', 'name', 'account_name', 'account_number', 'bank_name', 'instructions', 'qr_image_path']),
        ]);
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

        $this->wizard->submit($request->user(), $application, $request->validated('asba_reference'));

        return redirect()->route('applications.wizard')->with('success', 'Application submitted successfully.');
    }
}
