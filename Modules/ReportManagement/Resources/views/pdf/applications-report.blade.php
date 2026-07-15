<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Applications Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        .summary { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .summary td { border: 1px solid #d1d5db; padding: 6px 8px; }
        .summary .label { color: #6b7280; font-size: 9px; text-transform: uppercase; }
        .summary .value { font-size: 13px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f3f4f6; text-align: left; padding: 5px 6px; border: 1px solid #d1d5db; font-size: 9px; text-transform: uppercase; }
        table.data td { padding: 4px 6px; border: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>Prosperity MIS — Share Applications Report</h1>
    <p class="meta">
        Generated {{ $generatedAt->format('Y-m-d H:i') }}
        @if (count($filters))
            · Filters:
            @foreach ($filters as $key => $value)
                {{ str_replace('_', ' ', $key) }} = {{ $value }}@if (! $loop->last), @endif
            @endforeach
        @endif
    </p>

    <table class="summary">
        <tr>
            <td><div class="label">Applications</div><div class="value">{{ number_format($summary['totalApplications']) }}</div></td>
            <td><div class="label">Shares Applied</div><div class="value">{{ number_format($summary['totalShares']) }}</div></td>
            <td><div class="label">Total Declared</div><div class="value">Rs. {{ number_format((float) $summary['totalDeclared'], 2) }}</div></td>
            <td><div class="label">Verified Payments</div><div class="value">Rs. {{ number_format((float) $summary['totalVerifiedPayments'], 2) }}</div></td>
            <td><div class="label">Shares Allotted</div><div class="value">{{ number_format($summary['totalAllotted']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Application No</th>
                <th>Applicant</th>
                <th>Company / Offering</th>
                <th>Shares</th>
                <th>Rate</th>
                <th>Total</th>
                <th>Verified</th>
                <th>Allotted</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
                <tr>
                    <td>{{ $application->application_number }}</td>
                    <td>{{ $application->applicant?->full_name_english }}</td>
                    <td>
                        {{ $application->offering?->company?->name ?? '-' }}
                        @if ($application->offering) / {{ $application->offering->title }} ({{ $application->offering->fiscal_year }}) @endif
                    </td>
                    <td>{{ number_format($application->shares_applied) }}</td>
                    <td>{{ $application->amount_per_share }}</td>
                    <td>{{ $application->total_amount_declared }}</td>
                    <td>{{ $application->verified_amount ?? '0.00' }}</td>
                    <td>{{ $application->allotment?->shares_allotted ?? 0 }}</td>
                    <td>{{ $application->status }}</td>
                    <td>{{ $application->created_at?->toDateString() }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No applications match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
