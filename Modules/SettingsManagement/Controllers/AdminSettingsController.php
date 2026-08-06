<?php

namespace Modules\SettingsManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Modules\SettingsManagement\Models\Setting;
use Modules\SettingsManagement\Requests\UpdateSettingsRequest;

class AdminSettingsController extends Controller
{
    public function index()
    {
        $values = Setting::allCached();

        $settings = [];
        foreach (UpdateSettingsRequest::fields() as $key => $field) {
            // the SMTP password is write-only: never send it to the browser
            $settings[$field['group']][$key] = $key === 'mail_password' ? '' : ($values[$key] ?? '');
        }

        // include image settings explicitly so the form can preview them
        $settings['organization']['org_logo'] = $values['org_logo'] ?? '';
        $settings['organization']['org_stamp'] = $values['org_stamp'] ?? '';
        $settings['organization']['receipt_verifier_user_id'] = $values['receipt_verifier_user_id'] ?? '';
        $settings['organization']['receipt_reviewer_user_id'] = $values['receipt_reviewer_user_id'] ?? '';
        $settings['organization']['receipt_approver_user_id'] = $values['receipt_approver_user_id'] ?? '';

        $signUsers = User::query()
            ->select(['id', 'name', 'email'])
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values(),
            ]);

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
            'signUsers' => $signUsers,
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $changed = [];

        foreach (UpdateSettingsRequest::fields() as $key => $field) {
            // file handling for image settings
            if (in_array($key, ['org_logo', 'org_stamp'], true) && $request->hasFile($key)) {
                $file = $request->file($key);
                $path = $file->store('settings', 'public');
                $value = '/storage/' . $path;
            } else {
                $value = $request->validated($key);
            }

            // blank password means "keep the current one"
            if ($key === 'mail_password' && blank($value)) {
                continue;
            }

            if ((string) $value !== (string) Setting::get($key, '')) {
                Setting::set($key, $value, $field['group']);
                $changed[] = $key;
            }
        }

        if ($changed !== []) {
            activity('settings')
                ->causedBy($request->user())
                ->withProperties([
                    'changed' => $changed,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('Site settings updated');
        }

        return back()->with('success', 'Settings saved.');
    }
}
