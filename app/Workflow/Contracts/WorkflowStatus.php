<?php

namespace App\Workflow\Contracts;

use App\Enums\WorkflowStage;

/**
 * A status enum that participates in the three-stage review chain.
 *
 * Statuses name the work already completed; the stage waiting to act is
 * derived from that via pendingStage(). Statuses outside the chain (drafts,
 * allotment and refund states) simply return null and the engine refuses to
 * act on them.
 */
interface WorkflowStatus
{
    /** The stage whose sign-off this status is waiting for, or null if not in the chain. */
    public function pendingStage(): ?WorkflowStage;

    /** Status once the pending stage approves. */
    public function advance(): static;

    /** Status when the pending stage sends the record one stage back, or null at the first stage. */
    public function sendBackTarget(): ?static;

    /** Status when a stage returns the record to the applicant for correction. */
    public static function returned(): static;

    /** Where a corrected record re-enters the chain when the applicant resubmits. */
    public static function chainStart(): static;
}
