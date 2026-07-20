<?php

namespace Modules\ApprovalManagement\Requests;

use App\Enums\WorkflowAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Holding any application stage permission gets you in the door; whether you
 * may act on this particular record is WorkflowService's call.
 */
class ApplicationWorkflowActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission([
            'application.verify', 'application.review', 'application.approve',
        ]) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(WorkflowAction::class)],
            'remarks' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function action(): WorkflowAction
    {
        return WorkflowAction::from($this->validated('action'));
    }
}
