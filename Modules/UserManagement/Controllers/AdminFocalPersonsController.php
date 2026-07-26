<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\UserManagement\Repositories\FocalPersonRepository;
use Modules\UserManagement\Services\FocalPersonService;

/**
 * Designating focal persons. Any user with an approved KYC profile qualifies,
 * whatever their role — the designation is an attribution label and grants no
 * abilities, so it deliberately lives outside the role editor (which syncs a
 * single role and would otherwise overwrite the one they already hold).
 */
class AdminFocalPersonsController extends Controller
{
    public function __construct(
        private FocalPersonRepository $focalPersons,
        private FocalPersonService $service,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $taggedOnly = $request->boolean('tagged');

        return Inertia::render('Admin/FocalPersons', [
            'candidates' => $this->focalPersons->candidates($search ?: null, $taggedOnly),
            'filters' => ['search' => $search, 'tagged' => $taggedOnly],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_focal_person' => ['required', 'boolean'],
        ]);

        // grant() rejects an unapproved KYC profile with a validation error, so
        // the eligibility rule cannot be bypassed by posting straight here.
        $validated['is_focal_person']
            ? $this->service->grant($user)
            : $this->service->revoke($user);

        return redirect()->route('admin.focal-persons', $request->only('search', 'tagged'))
            ->with('success', $validated['is_focal_person']
                ? "{$user->name} is now a focal person (code {$user->fresh()->focal_person_code})."
                : "{$user->name} is no longer a focal person.");
    }
}
