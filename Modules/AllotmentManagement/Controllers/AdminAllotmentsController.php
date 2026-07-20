<?php

namespace Modules\AllotmentManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\AllotmentManagement\Repositories\ShareAllotmentRepository;

class AdminAllotmentsController extends Controller
{
    public function __construct(private ShareAllotmentRepository $allotments) {}

    public function index(Request $request)
    {
        $allotments = $this->allotments->listForAdmin();
        $totalAllotted = $this->allotments->totalShares();
        $totalApplicants = $this->allotments->distinctApplicationCount();

        return Inertia::render('Admin/Allotments', [
            'allotments' => $allotments,
            'stats' => [
                'totalAllotted' => $totalAllotted,
                'totalApplicants' => $totalApplicants,
                'averagePerApplicant' => $totalApplicants > 0 ? round($totalAllotted / $totalApplicants) : 0,
                'totalRaised' => $this->allotments->totalRaised(),
            ],
        ]);
    }
}
