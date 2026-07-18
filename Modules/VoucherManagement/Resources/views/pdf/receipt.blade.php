<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $companyName }} Payment Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; margin: 24px; }
        .header { text-align: center; }
        .company-name { font-size: 22px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .company-address { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .title { text-align: center; font-size: 15px; font-weight: bold; letter-spacing: 1px; margin: 14px 0 10px; }
        .meta { width: 100%; margin-top: 14px; }
        .meta td { vertical-align: top; }
        .fill { display: inline-block; border-bottom: 1px solid #1a1a1a; padding: 0 6px 1px; font-weight: bold; min-width: 60px; }
        .para { line-height: 2.1; text-align: justify; margin-top: 8px; }
        .modes { margin-top: 14px; line-height: 2; }
        .cb { display: inline-block; width: 11px; height: 11px; border: 1.4px solid #1a1a1a; text-align: center;
              font-size: 10px; line-height: 11px; font-weight: bold; margin-right: 3px; }
        .mode-item { display: inline-block; margin-right: 14px; white-space: nowrap; }
        .signatures { width: 100%; margin-top: 55px; }
        .signatures td { width: 33%; vertical-align: bottom; }
        .sig-line { border-top: 1px dotted #1a1a1a; display: inline-block; min-width: 160px; padding-top: 3px; font-weight: bold; }
        .stamp { text-align: center; color: #555; font-weight: bold; }
        .verify { margin-top: 28px; font-size: 10px; color: #444; }
    </style>
</head>
<body>

<div class="header">
    @if (!empty($logoDataUri))
        <img src="{{ $logoDataUri }}" alt="Logo" style="height:56px; margin-bottom:4px;">
    @endif
    <div class="company-name">{{ $companyName }}</div>
    @if ($companyAddress)
        <div class="company-address">{{ $companyAddress }}</div>
    @endif
</div>

<table class="meta">
    <tr>
        <td style="width:60%;">
            <div>Receipt No.: <span class="fill">{{ $payment->receipt_number }}</span></div>
            <div style="margin-top:8px;">Date: <span class="fill">{{ optional($voucher->generated_at)->format('jS F, Y') }}</span></div>
        </td>
        <td style="width:40%; text-align:right; font-size:10px;">
            Voucher No: <strong>{{ $voucher->voucher_number }}</strong><br>
            Application No: <strong>{{ $application->application_number }}</strong>
        </td>
    </tr>
</table>

<div class="title">PAYMENT RECEIPT</div>

<div class="para">
    We hereby acknowledge the receipt of payment amounting to
    Rs. <span class="fill">{{ number_format((float) $payment->amount, 2) }}/-</span>
    (In Words: Nepalese Rupees <span class="fill">{{ $amountInEnglishWords }}</span> Only)
    for the purpose of <span class="fill">Share Capital of our Company</span>
    from Mr./Mrs./Ms. <span class="fill">{{ $application->applicant->full_name_en ?? '-' }}</span>,
    holding ID No. <span class="fill">{{ $payment->holding_id_no ?: '................' }}</span>
    ID Type <span class="fill">{{ $payment->id_type ? ucwords(str_replace('_', ' ', $payment->id_type)) : '................' }}</span>.
</div>

<div class="modes">
    <strong>Mode of Payment:</strong>
    @foreach ([
        'cheque' => 'Cheque',
        'self_cheque_deposit' => 'Self Cheque Deposit',
        'online_transfer' => 'Online Transfer',
        'cash' => 'Cash',
        'ips' => 'IPS',
        'mobile_banking' => 'Mobile Banking',
    ] as $mode => $label)
        <span class="mode-item"><span class="cb">{{ $payment->payment_mode === $mode ? 'X' : '' }}</span>{{ $label }}</span>
    @endforeach
    <br>
    Payment Reference No: <span class="fill">{{ $payment->payment_reference_no ?: ($payment->cheque_no ?: '................') }}</span><br>
    Date of Payment: <span class="fill">{{ optional($payment->payment_date)->format('j F, Y') ?: '................' }}</span>
</div>

<table class="signatures">
    <tr>
        <td style="text-align:left;"><span class="sig-line">Issued By:</span></td>
        <td class="stamp">Company Stamp</td>
        <td style="text-align:right;"><span class="sig-line">Approved By:</span></td>
    </tr>
</table>

@isset($verificationQr)
<table class="verify" width="100%">
    <tr>
        <td style="width:70px;"><img src="{{ $verificationQr }}" alt="Verification QR" width="62" height="62"></td>
        <td style="padding-left:8px;">
            Verification Code: <strong>{{ $voucher->verification_code }}</strong><br>
            Verify this receipt at {{ $verificationUrl }}
        </td>
    </tr>
</table>
@endisset

</body>
</html>
