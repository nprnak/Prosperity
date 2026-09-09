<?php

namespace App\Http\Controllers;

use App\Services\NepaliDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the Nepali (Bikram Sambat) date picker: the calendar's month lengths
 * are irregular, so the picker asks here rather than guessing client-side.
 */
class NepaliDateController extends Controller
{
    public function daysInMonth(Request $request, NepaliDateService $dates): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $days = $dates->daysInBsMonth($validated['year'], $validated['month']);

        return response()->json(['days' => $days]);
    }

    public function toNepali(Request $request, NepaliDateService $dates): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $parts = $dates->toBikramSambatParts(\Carbon\Carbon::parse($validated['date']));

        if (! $parts) {
            return response()->json(['message' => 'That date is outside the supported range (AD 1944-2033).'], 422);
        }

        return response()->json($parts);
    }

    public function toEnglish(Request $request, NepaliDateService $dates): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'day' => ['required', 'integer', 'min:1', 'max:32'],
        ]);

        $date = $dates->toGregorian($validated['year'], $validated['month'], $validated['day']);

        if (! $date) {
            return response()->json(['message' => 'That is not a valid date on the Nepali calendar.'], 422);
        }

        return response()->json(['date' => $date]);
    }
}
