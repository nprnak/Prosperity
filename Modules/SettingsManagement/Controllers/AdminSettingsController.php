<?php

namespace Modules\SettingsManagement\Controllers;

use App\Http\Controllers\Controller;
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

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $changed = [];

        foreach (UpdateSettingsRequest::fields() as $key => $field) {
            $value = $request->validated($key);

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
