<?php

namespace App\Models;

use App\Enums\WorkflowAction;
use App\Enums\WorkflowStage;
use Illuminate\Database\Eloquent\Model;

/**
 * One recorded action by one person at one stage. Append-only: the engine
 * writes these and nothing updates them.
 */
class WorkflowEvent extends Model
{
    protected $fillable = [
        'actor_id', 'stage', 'action', 'from_status', 'to_status', 'remarks', 'cycle', 'meta',
    ];

    protected $casts = [
        'stage' => WorkflowStage::class,
        'action' => WorkflowAction::class,
        'meta' => 'array',
    ];

    protected $appends = ['stage_label', 'action_label'];

    /** Wording comes from the enums so views never re-map it. */
    public function getStageLabelAttribute(): ?string
    {
        return $this->stage?->labelEn();
    }

    public function getActionLabelAttribute(): ?string
    {
        return $this->action?->labelEn();
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
