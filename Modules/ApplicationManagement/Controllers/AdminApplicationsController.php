<?php

namespace Modules\ApplicationManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Services\ProfileDocumentService;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminApplicationsController extends Controller
{
    public function __construct(private ShareApplicationRepository $applications) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Applications', [
            'applications' => $this->applications->listForAdmin(),
        ]);
    }

    public function show(Request $request, ShareApplication $application): Response
    {
        // Verifier stage must read the form before signing it off.
        if ($request->user()) {
            Cache::put($this->viewedCacheKey($request->user()->id, $application->id), true, now()->addHours(8));
        }

        $application = $this->applications->loadDetail($application);

        $payment = $application->paymentTransactions->first();
        $receiptVoucher = $payment?->voucher;

        return Inertia::render('Admin/ApplicationShow', [
            'application' => $application,
            'receipt' => $receiptVoucher ? [
                'receiptNumber' => $payment->receipt_number,
                'showUrl' => route('vouchers.show', $receiptVoucher),
                'previewUrl' => route('vouchers.preview', $receiptVoucher),
                'downloadUrl' => route('vouchers.download', $receiptVoucher),
            ] : null,
            ...$this->backLink($request),
            // The paper-entry flow is a verify-stage job; shown here too so a
            // verifier reviewing one application can jump straight to filing
            // another without detouring through their dashboard.
            'addApplicationUrl' => $request->user()?->can('application.verify')
                ? route('applications.add.pick') : null,
            // A verifier's own paper application, sent back by a later stage
            // (or still a draft), reopens in the same wizard they filed it in
            // — the paper-entry flow already knows how to resume an editable
            // application for its applicant, so this just links straight to it
            // rather than making the verifier search the picker again.
            'editApplicationUrl' => $this->editApplicationUrl($request, $application),
            // Only offered when the scan is actually on file, so the print
            // action never opens onto a 404.
            'citizenshipUrls' => collect(['front' => 'Front', 'back' => 'Back'])
                ->map(fn ($label, $side) => [
                    'side' => $side,
                    'label' => $label,
                    'url' => $this->hasDocument($application, "citizenship_{$side}")
                        ? route('admin.applications.citizenship', [$application->id, $side])
                        : null,
                ])
                ->filter(fn ($doc) => $doc['url'] !== null)
                ->values(),
            // The reviewing stages need to see the applicant's face and
            // signature too, not just the applicant's own copy of the form.
            'photoUrl' => $this->hasDocument($application, 'photo')
                ? route('admin.applications.photo', $application->id) : null,
            'signatureUrl' => $this->hasDocument($application, 'signature')
                ? route('admin.applications.signature', $application->id) : null,
        ]);
    }

    /**
     * The applicant's citizenship scan, for staff who may already view the
     * application. Authorised by the route's application.view-any permission.
     */
    public function citizenship(ShareApplication $application, string $side, ProfileDocumentService $documents): BinaryFileResponse
    {
        $applicant = $application->applicant;

        abort_unless($applicant instanceof Profile, 404);

        return $documents->respond($applicant, "citizenship-{$side}");
    }

    public function photo(ShareApplication $application, ProfileDocumentService $documents): BinaryFileResponse
    {
        $applicant = $application->applicant;

        abort_unless($applicant instanceof Profile, 404);

        return $documents->respond($applicant, 'photo');
    }

    public function signature(ShareApplication $application, ProfileDocumentService $documents): BinaryFileResponse
    {
        $applicant = $application->applicant;

        abort_unless($applicant instanceof Profile, 404);

        return $documents->respond($applicant, 'signature');
    }

    /**
     * Where "Back" goes: the applications list for whoever can actually
     * browse it (finance, admin), or a review-chain stage's own queue for
     * whoever holds only their stage's permission and would otherwise land
     * on a 403 — the applications list is no longer in their sidebar at all.
     *
     * @return array{backUrl: string, backLabel: string}
     */
    private function backLink(Request $request): array
    {
        $user = $request->user();

        if ($user?->can('application.view-any')) {
            return ['backUrl' => route('admin.applications'), 'backLabel' => 'Back to Applications'];
        }

        // The three review-chain stages share one queue (applications.review),
        // so there's only one destination for all of them.
        if ($user?->can('application.verify') || $user?->can('application.review') || $user?->can('application.approve')) {
            return ['backUrl' => route('applications.review'), 'backLabel' => 'Back to Application Review'];
        }

        return ['backUrl' => route('admin.applications'), 'backLabel' => 'Back to Applications'];
    }

    /**
     * Only the verifier who filed this exact paper application gets a direct
     * link to correct it — and only while it is still theirs to correct: a
     * draft they have not submitted yet, or one a later stage sent back.
     * Anyone else with application.verify still has the generic "Add
     * Application" picker for filing a different one.
     */
    private function editApplicationUrl(Request $request, ShareApplication $application): ?string
    {
        $user = $request->user();

        if (! $user
            || $application->entered_by !== $user->id
            || ! in_array($application->status, [ApplicationStatus::Draft, ApplicationStatus::Returned], true)
            || ! $application->applicant?->user_id
        ) {
            return null;
        }

        // ?resume=1 tells the wizard this link was an explicit "go fix it"
        // click, so it can skip straight to the pre-filled form instead of
        // the confirmation notice a plain "Add Application" visit gets.
        return route('applications.add.create', $application->applicant->user_id).'?resume=1';
    }

    private function hasDocument(ShareApplication $application, string $documentType): bool
    {
        return $application->applicant?->documents
            ->contains(fn ($document) => $document->document_type === $documentType) ?? false;
    }

    private function viewedCacheKey(int $userId, int $applicationId): string
    {
        return "application:viewed:{$userId}:{$applicationId}";
    }
}
