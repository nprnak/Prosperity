<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\JarManagement\Services\JarRegistrationService;

/**
 * Standalone registration — minting brand-new jar codes ahead of production
 * (printed as labels before the jars are ever scanned into a batch). The
 * in-the-field "uncoded jar found at collection" case goes through
 * DeliveryService instead, since there it's part of recording the return.
 */
class JarRegistrationController extends Controller
{
    public function __construct(private readonly JarRegistrationService $service) {}

    public function store(Request $request)
    {
        $request->validate(['count' => ['required', 'integer', 'min:1', 'max:200']]);

        abort_unless($request->user()->can('jar.production.manage'), 403);

        $jars = $this->service->registerMany($request->user(), $request->integer('count'));

        return back()->with('success', "{$jars->count()} jar code(s) registered: {$jars->pluck('jar_code')->implode(', ')}");
    }
}
