<?php

namespace Modules\ApplicantManagement\Requests;

use App\Enums\WorkflowAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorisation here only checks the user holds *some* KYC stage role;
 * WorkflowService decides whether they may take the stage this record is
 * actually waiting on, and enforces the act-once rule.
 */
class ProfileWorkflowActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission([
            'profile.verify', 'profile.review', 'profile.approve',
        ]) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(WorkflowAction::class)],
            // Every action must be justified — this is the audit trail.
            'remarks' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function action(): WorkflowAction
    {
        return WorkflowAction::from($this->validated('action'));
    }
}
