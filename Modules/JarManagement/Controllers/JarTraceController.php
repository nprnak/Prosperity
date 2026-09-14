<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\JarManagement\Repositories\JarRepository;
use Modules\JarManagement\Services\JarTraceService;

class JarTraceController extends Controller
{
    public function __construct(
        private readonly JarTraceService $trace,
        private readonly JarRepository $jars,
    ) {}

    public function index(Request $request)
    {
        $code = $request->string('jar_code')->trim()->upper()->toString();
        $jar = null;
        $error = null;

        if ($code !== '') {
            try {
                $jar = $this->trace->trace($code);
            } catch (ValidationException $e) {
                $error = collect($e->errors())->flatten()->first();
            }
        }

        return Inertia::render('JarManagement/Reports/JarTrace', [
            'jarCode' => $code,
            'jar' => $jar,
            'error' => $error,
            'recentJars' => $this->jars->search(null, null, 10),
        ]);
    }
}
