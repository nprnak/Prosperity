<?php

namespace Modules\ApplicationManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Services\ProfileDocumentService;
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
        $application = $this->applications->loadDetail($application);

        return Inertia::render('Admin/ApplicationShow', [
            'application' => $application,
            // Only offered when the scan is actually on file, so the print
            // action never opens onto a 404.
            'citizenshipUrls' => collect(['front' => 'Front', 'back' => 'Back'])
                ->map(fn ($label, $side) => [
                    'side' => $side,
                    'label' => $label,
                    'url' => $this->hasCitizenship($application, $side)
                        ? route('admin.applications.citizenship', [$application->id, $side])
                        : null,
                ])
                ->filter(fn ($doc) => $doc['url'] !== null)
                ->values(),
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

    private function hasCitizenship(ShareApplication $application, string $side): bool
    {
        return $application->applicant?->documents
            ->contains(fn ($document) => $document->document_type === "citizenship_{$side}") ?? false;
    }
}
