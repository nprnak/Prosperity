<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;

/** Application stage 1: acts on payment-verified applications. */
class VerifierController extends ApplicationStageController
{
    protected function stage(): WorkflowStage
    {
        return WorkflowStage::Verifier;
    }

    protected function view(): string
    {
        return 'Verifier/Dashboard';
    }
}
