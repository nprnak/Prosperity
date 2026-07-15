<?php

namespace Modules\ApplicationManagement\Controllers;

use App\Http\Controllers\Controller;
use Modules\ApplicationManagement\Models\ShareApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminApplicationsController extends Controller
{
    public function index(Request $request): Response
    {
        $applications = ShareApplication::with('applicant')->latest()->get();

        return Inertia::render('Admin/Applications', [
            'applications' => $applications,
        ]);
    }

    public function show(Request $request, ShareApplication $application): Response
    {
        $application->load([
            'applicant',
            'reviewer:id,name,email',
            'allotment',
            'paymentTransactions' => fn ($query) => $query->with('voucher')->latest(),
            'events' => fn ($query) => $query->with('actor:id,name')->latest(),
        ]);

        return Inertia::render('Admin/ApplicationShow', [
            'application' => $application,
        ]);
    }
}
