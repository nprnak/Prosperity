<?php

namespace Modules\JarManagement\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\JarManagement\Models\Jar;
use Modules\JarManagement\Repositories\JarCustomerRepository;

/**
 * Backs the shared JarScanInput component's instant "is this a valid jar,
 * what's its status" feedback, and the delivery screen's customer typeahead.
 */
class JarLookupController extends Controller
{
    public function jar(string $jarCode)
    {
        $jar = Jar::where('jar_code', strtoupper(trim($jarCode)))->first();

        if (! $jar) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found' => true,
            'jar_code' => $jar->jar_code,
            'status' => $jar->status->value,
            'status_label' => $jar->status->labelEn(),
            'condition' => $jar->condition->value,
        ]);
    }

    public function customers(Request $request, JarCustomerRepository $customers)
    {
        $term = trim((string) $request->query('q', ''));

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json($customers->quickSearch($term));
    }
}
