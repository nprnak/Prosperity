<?php

namespace Modules\ApplicationManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;

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
        return Inertia::render('Admin/ApplicationShow', [
            'application' => $this->applications->loadDetail($application),
        ]);
    }
}
