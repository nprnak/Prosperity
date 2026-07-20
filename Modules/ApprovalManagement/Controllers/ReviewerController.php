<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;

/** Application stage 2: acts on verified applications. */
class ReviewerController extends ApplicationStageController
{
    protected function stage(): WorkflowStage
    {
        return WorkflowStage::Reviewer;
    }

    protected function view(): string
    {
        return 'Reviewer/Dashboard';
    }
}
