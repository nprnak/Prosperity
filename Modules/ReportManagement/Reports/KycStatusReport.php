<?php

namespace Modules\ReportManagement\Reports;

use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;

/**
 * KYC Status Report — who has completed KYC and who is still outstanding,
 * filterable by the stage a profile is sitting at.
 */
class KycStatusReport extends BaseReport
{
    public function key(): string
    {
        return 'kyc-status';
    }

    public function title(): string
    {
        return 'KYC Status Report';
    }

    public function description(): string
    {
        return 'Every registered shareholder and the stage their KYC profile has reached, '
            .'filterable by that stage.';
    }

    public function filters(): array
    {
        return [
            [
                'key' => 'profile_status',
                'label' => 'KYC Status',
                'type' => 'select',
                'placeholder' => 'All statuses',
                'options' => array_map(fn (ProfileStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->labelEn(),
                ], ProfileStatus::cases()),
            ],
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            ['key' => 'sn', 'label' => 'S.N.', 'align' => 'right'],
            ['key' => 'username', 'label' => 'Username'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'citizenship_number', 'label' => 'Citizenship No.'],
            ['key' => 'kyc_status', 'label' => 'KYC Update Status'],
            ['key' => 'kyc_completion', 'label' => 'Completion', 'align' => 'right'],
            ['key' => 'submitted_at', 'label' => 'Submitted On', 'default' => false],
            ['key' => 'email', 'label' => 'Mail ID', 'default' => false],
            ['key' => 'phone', 'label' => 'Phone No.', 'default' => false],
        ];
    }

    public function rows(array $filters = []): array
    {
        $profiles = Profile::query()
            // completionPercent() reads the address and documents, so they are
            // eager loaded rather than fetched per row.
            ->with(['user:id,name,email', 'permanentAddress', 'documents:id,profile_id,document_type'])
            ->when(
                $filters['profile_status'] ?? null,
                fn ($q, $status) => $q->where('profile_status', $status),
            )
            ->orderBy('full_name_en')
            ->get();

        $serial = 0;

        return $profiles->map(fn (Profile $profile) => [
            'sn' => ++$serial,
            'username' => $profile->user?->name ?: '—',
            'name' => $profile->full_name_en ?: ($profile->full_name_np ?: '—'),
            'citizenship_number' => $profile->citizenship_number ?: '—',
            'kyc_status' => $profile->profile_status?->labelEn() ?: '—',
            'kyc_completion' => $profile->completionPercent().'%',
            'submitted_at' => $profile->profile_submitted_at?->format('Y-m-d') ?: '—',
            'email' => $profile->email ?: $profile->user?->email ?: '—',
            'phone' => $profile->mobile ?: '—',
        ])->all();
    }
}
