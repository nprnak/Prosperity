<?php

namespace Modules\SettingsManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Every editable setting key, its group, and its validation rules.
     * Keys not listed here are ignored on update.
     *
     * @return array<string, array{group: string, rules: array}>
     */
    public static function fields(): array
    {
        return [
            'org_name' => ['group' => 'organization', 'rules' => ['required', 'string', 'max:255']],
            'org_address' => ['group' => 'organization', 'rules' => ['nullable', 'string', 'max:500']],
            'contact_email' => ['group' => 'organization', 'rules' => ['required', 'email', 'max:255']],
            'support_phone' => ['group' => 'organization', 'rules' => ['nullable', 'string', 'max:50']],

            'mail_host' => ['group' => 'mail', 'rules' => ['nullable', 'string', 'max:255']],
            'mail_port' => ['group' => 'mail', 'rules' => ['nullable', 'integer', 'between:1,65535']],
            'mail_username' => ['group' => 'mail', 'rules' => ['nullable', 'string', 'max:255']],
            'mail_password' => ['group' => 'mail', 'rules' => ['nullable', 'string', 'max:255']],
            'mail_encryption' => ['group' => 'mail', 'rules' => ['nullable', Rule::in(['tls', 'ssl', 'none'])]],
            'mail_from_address' => ['group' => 'mail', 'rules' => ['nullable', 'email', 'max:255']],
            'mail_from_name' => ['group' => 'mail', 'rules' => ['nullable', 'string', 'max:255']],

            'currency_code' => ['group' => 'application', 'rules' => ['required', 'string', 'size:3']],
            'currency_symbol' => ['group' => 'application', 'rules' => ['required', 'string', 'max:10']],
            'max_upload_size_kb' => ['group' => 'application', 'rules' => ['required', 'integer', 'between:64,51200']],
            'max_applications_per_user' => ['group' => 'application', 'rules' => ['required', 'integer', 'between:1,100']],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return array_map(fn ($field) => $field['rules'], static::fields());
    }
}
