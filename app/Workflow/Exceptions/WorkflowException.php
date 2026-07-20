<?php

namespace App\Workflow\Exceptions;

use Exception;

/**
 * A refused workflow transition. Surfaces as 422 so controllers can let it
 * bubble rather than each re-checking the guards.
 */
class WorkflowException extends Exception
{
    public function render($request)
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['workflow' => $this->getMessage()]);
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
