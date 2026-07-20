<?php

namespace App\Workflow;

use App\Enums\WorkflowAction;
use App\Enums\WorkflowStage;
use App\Models\User;
use App\Workflow\Exceptions\WorkflowException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Drives both review chains. Every transition goes through act(), so the
 * guards below are the single place the controls are enforced.
 *
 * The controls, in order:
 *   1. the record is actually in the chain and awaiting this stage
 *   2. the actor holds the stage permission for this subject
 *   3. the actor has not already occupied a different stage this cycle
 *   4. remarks are present
 */
class WorkflowService
{
    /**
     * @throws WorkflowException
     */
    public function act(
        Model $subject,
        User $actor,
        WorkflowAction $action,
        string $remarks,
        ?Request $request = null,
    ): Model {
        $status = $subject->workflowStatus();
        $stage = $status?->pendingStage();

        if ($stage === null) {
            throw new WorkflowException(
                'This record is not awaiting a review action (status: '
                .($status?->labelEn() ?? 'none').').'
            );
        }

        $remarks = trim($remarks);

        if ($remarks === '') {
            throw new WorkflowException('Remarks are required for every workflow action.');
        }

        $this->assertMayAct($subject, $actor, $stage);

        $target = $this->targetStatus($status, $stage, $action);

        return DB::transaction(function () use ($subject, $actor, $stage, $action, $status, $target, $remarks, $request) {
            // Assign the backing value so this works on cast and uncast columns alike.
            $subject->forceFill([
                $subject->workflowStatusColumn() => $target->value,
            ])->save();

            $subject->workflowEvents()->create([
                'actor_id' => $actor->id,
                'stage' => $stage,
                'action' => $action,
                'from_status' => $status->value,
                'to_status' => $target->value,
                'remarks' => $remarks,
                'cycle' => $subject->workflow_cycle,
                'meta' => [
                    'ip' => $request?->ip(),
                    'user_agent' => $request?->userAgent(),
                ],
            ]);

            return $subject->refresh();
        });
    }

    /**
     * Whether this user could act on this record right now. Drives both the
     * guard in act() and the UI's decision to show the action buttons.
     */
    public function mayAct(Model $subject, User $actor, ?WorkflowStage $stage = null): bool
    {
        try {
            $stage ??= $subject->pendingStage();

            if ($stage === null) {
                return false;
            }

            $this->assertMayAct($subject, $actor, $stage);

            return true;
        } catch (WorkflowException) {
            return false;
        }
    }

    /**
     * @throws WorkflowException
     */
    protected function assertMayAct(Model $subject, User $actor, WorkflowStage $stage): void
    {
        if (! $actor->can($stage->permission($subject->workflowSubject()))) {
            throw new WorkflowException("You do not hold the {$stage->labelEn()} role for this record.");
        }

        // The segregation-of-duties control: three distinct people per cycle.
        // Repeating your *own* stage is fine — that is what happens when a
        // later stage sends the record back for another look. Super admins are
        // deliberately not exempt.
        $occupied = $subject->stagesActedByUser($actor->id);
        $others = array_diff($occupied, [$stage->value]);

        if ($others !== []) {
            throw new WorkflowException(
                'You have already acted on this record at the '
                .implode(', ', $others).' stage, so you cannot also act as '
                .$stage->value.'. A separate person must take this stage.'
            );
        }
    }

    /**
     * @throws WorkflowException
     */
    protected function targetStatus($status, WorkflowStage $stage, WorkflowAction $action)
    {
        return match ($action) {
            WorkflowAction::Approve => $status->advance(),
            WorkflowAction::ReturnToApplicant => $status::returned(),
            WorkflowAction::SendBack => $status->sendBackTarget()
                ?? throw new WorkflowException(
                    'This record is with the first stage, so there is nothing to send it back to. '
                    .'Return it to the applicant instead.'
                ),
        };
    }
}
