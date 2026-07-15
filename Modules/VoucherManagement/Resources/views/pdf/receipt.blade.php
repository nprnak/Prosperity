<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ \Modules\SettingsManagement\Models\Setting::get('org_name', 'Prosperity') }} Payment Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 12px; }
        .title { font-size: 16px; font-weight: bold; }
        .row { margin-bottom: 6px; }
        .label { font-weight: bold; display: inline-block; width: 180px; }
        .box { border: 1px solid #000; padding: 12px; }
        .signatures { margin-top: 40px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
<div class="header">
    <div class="title">{{ \Modules\SettingsManagement\Models\Setting::get('org_name', 'Prosperity Holdings Limited') }}</div>
    @if ($address = \Modules\SettingsManagement\Models\Setting::get('org_address'))
        <div>{{ $address }}</div>
    @endif
    <div>Payment Receipt / Voucher</div>
</div>

<div class="box">
    <div class="row"><span class="label">Voucher No:</span> {{ $voucher->voucher_number }}</div>
    <div class="row"><span class="label">Receipt No:</span> {{ $payment->receipt_number }}</div>
    <div class="row"><span class="label">Date:</span> {{ optional($payment->payment_date)->format('Y-m-d') }}</div>
    <div class="row"><span class="label">Applicant Name:</span> {{ $application->applicant->full_name_english ?? '-' }}</div>
    <div class="row"><span class="label">Application No:</span> {{ $application->application_number }}</div>
    <div class="row"><span class="label">Amount:</span> {{ \Modules\SettingsManagement\Models\Setting::get('currency_code', 'NPR') }} {{ $payment->amount }}</div>
    <div class="row"><span class="label">Amount in Words:</span> {{ $amountInWords }}</div>
    <div class="row"><span class="label">Purpose:</span> Founder Share Purchase</div>
    <div class="row"><span class="label">Holding ID:</span> {{ $payment->holding_id_no }}</div>
    <div class="row"><span class="label">ID Type:</span> {{ $payment->id_type }}</div>
    <div class="row"><span class="label">Payment Mode:</span> {{ $payment->payment_mode }}</div>
    <div class="row"><span class="label">Reference:</span> {{ $payment->payment_reference_no ?? $payment->cheque_no }}</div>
</div>

@isset($verificationQr)
<div style="margin-top:16px; text-align:center;">
    <img src="{{ $verificationQr }}" alt="Verification QR" width="110" height="110">
    <div style="font-size:11px; margin-top:4px;">
        Verification Code: <strong>{{ $voucher->verification_code }}</strong><br>
        Verify this voucher at {{ $verificationUrl }}
    </div>
</div>
@endisset

<div class="signatures">
    <div>Issued By: ____________________</div>
    <div>Approved By: ____________________</div>
</div>
<div style="margin-top:20px;">Company Stamp: ____________________</div>
</body>
</html>
