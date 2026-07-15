<?php

namespace Modules\SettingsManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSettingsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        return Inertia::render('Admin/Settings', [
            'settings' => [],
        ]);
    }
}
