<?php

namespace App\Http\Controllers;

use App\Http\Requests\Application\StoreDraftStepRequest;
use App\Http\Requests\Application\SubmitApplicationRequest;
use App\Models\Applicant;
use App\Models\ShareApplication;
use App\Notifications\ApplicationSubmittedNotification;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ApplicationWizardController extends Controller
{
    public function index(Request $request)
    {
        $draft = ShareApplication::query()
            ->whereHas('applicant', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', ShareApplication::STATUS_DRAFT)
            ->with('applicant')
            ->latest()
            ->first();

        $applications = ShareApplication::query()
            ->whereHas('applicant', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest()
            ->get();

        return Inertia::render('Applications/Wizard', [
            'draft' => $draft,
            'applications' => $applications,
        ]);
    }

    public function storeDraft(StoreDraftStepRequest $request)
    {
        $user = $request->user();
        $payload = $request->validated('payload');

        $applicant = Applicant::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name_nepali' => $payload['full_name_nepali'] ?? $user->name,
                'full_name_english' => $payload['full_name_english'] ?? $user->name,
                'date_of_birth' => $payload['date_of_birth'] ?? now()->subYears(18)->toDateString(),
                'age' => $payload['age'] ?? 18,
                'father_name' => $payload['father_name'] ?? '-',
                'grandfather_name' => $payload['grandfather_name'] ?? '-',
                'marital_status' => $payload['marital_status'] ?? 'single',
                'permanent_district' => $payload['permanent_district'] ?? '-',
                'permanent_municipality' => $payload['permanent_municipality'] ?? '-',
                'permanent_ward' => $payload['permanent_ward'] ?? '-',
                'mobile_number' => $payload['mobile_number'] ?? '-',
            ]
        );

        $uploadMap = [
            'photo' => 'photo_path',
            'citizenship_doc' => 'citizenship_doc_path',
            'national_id_doc' => 'national_id_doc_path',
            'pan_doc' => 'pan_doc_path',
        ];

        foreach ($uploadMap as $fileInput => $dbColumn) {
            if ($request->hasFile("payload.$fileInput")) {
                $payload[$dbColumn] = $request->file("payload.$fileInput")->store('applications', 'private');
            }
        }

        $applicant->fill($payload);
        $applicant->save();

        $application = ShareApplication::firstOrCreate(
            [
                'applicant_id' => $applicant->id,
                'status' => ShareApplication::STATUS_DRAFT,
            ],
            [
                'application_number' => 'DRAFT-'.str_pad((string) $applicant->id, 6, '0', STR_PAD_LEFT),
                'shares_applied' => $payload['shares_applied'] ?? 1,
                'amount_per_share' => $payload['amount_per_share'] ?? '100.00',
                'total_amount_declared' => $payload['total_amount_declared'] ?? '100.00',
            ]
        );

        $application->fill([
            'shares_applied' => $payload['shares_applied'] ?? $application->shares_applied,
            'amount_per_share' => $payload['amount_per_share'] ?? $application->amount_per_share,
            'total_amount_declared' => $payload['total_amount_declared'] ?? $application->total_amount_declared,
        ]);
        $application->save();

        return back()->with('success', 'Draft saved.');
    }

    public function submit(SubmitApplicationRequest $request, ShareApplication $application, NumberGeneratorService $numberGenerator)
    {
        $application->load('applicant');

        abort_unless($application->applicant?->user_id === $request->user()->id, 403);

        if (str_starts_with($application->application_number, 'DRAFT-')) {
            $application->application_number = $numberGenerator->generateApplicationNumber();
        }

        $application->status = ShareApplication::STATUS_SUBMITTED;
        $application->submitted_at = now();
        $application->save();

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationSubmittedNotification($application));
        }

        return redirect()->route('applications.wizard')->with('success', 'Application submitted successfully.');
    }
}
