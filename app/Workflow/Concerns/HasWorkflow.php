<?php

namespace App\Workflow\Concerns;

use App\Enums\WorkflowStage;
use App\Models\User;
use App\Models\WorkflowEvent;
use App\Workflow\Contracts\WorkflowStatus;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Applied to a model whose review chain the workflow engine drives.
 *
 * The using model must expose:
 *   - workflowSubject(): 'profile' | 'application' — the permission prefix
 *   - workflowStatusColumn(): the column holding its WorkflowStatus enum
 *   - workflowStatusEnum(): that enum's class name
 */
trait HasWorkflow
{
    public function workflowEvents(): MorphMany
    {
        return $this->morphMany(WorkflowEvent::class, 'subject')->latest('id');
    }

    /**
     * Columns stamped with who signed a stage off and when, keyed by stage.
     * A model that keeps no such columns returns nothing and is left alone.
     *
     * @return array<string, array{by: string, at: string}>
     */
    public function stageSignOffColumns(): array
    {
        return [];
    }

    /**
     * Stamp the acting user against the stage they have just approved.
     *
     * The workflow events are the authoritative trail, but they are a separate
     * table nobody joins on a list screen — so the record carries its own
     * "verified by / reviewed by" for the pages that show it.
     */
    public function recordStageSignOff(WorkflowStage $stage, User $actor): void
    {
        $columns = $this->stageSignOffColumns()[$stage->value] ?? null;

        if ($columns === null) {
            return;
        }

        $this->forceFill([
            $columns['by'] => $actor->id,
            $columns['at'] => now(),
        ])->save();
    }

    /** Actions recorded in the current cycle only; earlier passes are retired. */
    public function currentCycleEvents()
    {
        return $this->workflowEvents()->where('cycle', $this->workflow_cycle);
    }

    /**
     * Resolves whether or not the model casts its status column, so a model
     * can adopt the engine before its string constants have been swept away.
     */
    public function workflowStatus(): ?WorkflowStatus
    {
        $value = $this->{$this->workflowStatusColumn()};

        if ($value instanceof WorkflowStatus) {
            return $value;
        }

        // Null on an unsaved record, before the column default applies.
        if (blank($value)) {
            return null;
        }

        return ($this->workflowStatusEnum())::from($value);
    }

    public function pendingStage(): ?WorkflowStage
    {
        return $this->workflowStatus()?->pendingStage();
    }

    /**
     * The stages this user has already occupied in the current cycle. Acting
     * at any *other* stage is what the act-once rule forbids — repeating your
     * own stage after a send-back is allowed.
     */
    public function stagesActedByUser(int $userId): array
    {
        return $this->currentCycleEvents()
            ->where('actor_id', $userId)
            ->pluck('stage')
            ->map(fn ($stage) => $stage instanceof WorkflowStage ? $stage->value : $stage)
            ->unique()
            ->values()
            ->all();
    }

    /** Which stage this record is waiting on, for queue display. */
    public function getPendingStageLabelAttribute(): ?string
    {
        return $this->pendingStage()?->labelEn();
    }

    /**
     * Whether a send-back has anywhere to go. False at the first stage, where
     * the only way back is to the applicant.
     */
    public function getCanSendBackAttribute(): bool
    {
        return $this->workflowStatus()?->sendBackTarget() !== null;
    }

    /**
     * The remarks from the most recent action — what the applicant is shown
     * when a record comes back to them.
     */
    public function getLatestWorkflowRemarksAttribute(): ?string
    {
        return $this->workflowEvents()->first()?->remarks;
    }

    /**
     * Retire the current cycle's sign-offs. Called when the applicant edits a
     * returned or rejected record, so the chain restarts from the first stage.
     */
    public function restartWorkflowCycle(): void
    {
        $this->forceFill([
            'workflow_cycle' => $this->workflow_cycle + 1,
        ])->save();
    }
}
