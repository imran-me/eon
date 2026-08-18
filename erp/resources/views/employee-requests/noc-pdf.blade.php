<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $item->metadata['document_type'] ?? 'Certificate' }} — {{ $item->employee?->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1a1a1a;
            font-size: 13px;
            line-height: 1.7;
            padding: 40px 60px;
        }
        .letterhead {
            text-align: center;
            border-bottom: 3px double #1e3a5f;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .letterhead .company-name {
            font-size: 22px;
            font-weight: 700;
            color: #1e3a5f;
            letter-spacing: 1px;
        }
        .letterhead .company-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        .doc-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 24px 0 20px;
            text-decoration: underline;
            color: #1e3a5f;
        }
        .ref-line {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 20px;
        }
        .body-text {
            text-align: justify;
            margin-bottom: 14px;
        }
        .body-text strong { color: #1e3a5f; }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table td {
            padding: 7px 12px;
            border: 1px solid #cbd5e1;
            font-size: 12px;
        }
        .info-table td:first-child {
            font-weight: 600;
            background: #f1f5f9;
            width: 38%;
            color: #334155;
        }
        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .sig-block {
            text-align: center;
            width: 200px;
        }
        .sig-line {
            border-top: 1px solid #334155;
            margin-bottom: 6px;
        }
        .sig-label { font-size: 11px; color: #64748b; }
        .seal-box {
            text-align: center;
            margin-top: 40px;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px dashed #cbd5e1;
            padding-top: 12px;
        }
        @php
            $docLabels = [
                'experience_letter' => 'Experience Letter',
                'salary_certificate'=> 'Salary Certificate',
                'noc_visa'          => 'No Objection Certificate (Visa)',
                'noc_bank'          => 'No Objection Certificate (Bank)',
                'noc_loan'          => 'No Objection Certificate (Loan)',
            ];
            $docType    = $item->metadata['document_type'] ?? 'noc_visa';
            $docLabel   = $docLabels[$docType] ?? 'Certificate';
            $employee   = $item->employee;
            $purpose    = $item->metadata['purpose'] ?? 'Whom It May Concern';
            $issueDate  = now()->format('d F Y');
            $refNo      = 'epal/NOC/' . str_pad($item->id, 5, '0', STR_PAD_LEFT) . '/' . now()->format('Y');
        @endphp
    </style>
</head>
<body>

    <div class="letterhead">
        <div class="company-name">epal ERP — HR Department</div>
        <div class="company-sub">Human Resources Division &nbsp;·&nbsp; hr@epal.com.bd &nbsp;·&nbsp; Dhaka, Bangladesh</div>
    </div>

    <div class="doc-title">{{ $docLabel }}</div>

    <div class="ref-line">
        <span>Ref: {{ $refNo }}</span>
        <span>Date: {{ $issueDate }}</span>
    </div>

    <p class="body-text">To Whom It May Concern,</p>

    @if(in_array($docType, ['noc_visa','noc_bank','noc_loan']))
    <p class="body-text">
        This is to certify that <strong>{{ $employee?->name }}</strong>
        @if($employee?->employee_id ?? false)
            (Employee ID: <strong>{{ $employee->employee_id }}</strong>)
        @endif
        is a bona fide employee of this organization. This No Objection Certificate is issued
        upon the employee's request for <strong>{{ $purpose }}</strong> purposes.
    </p>
    <p class="body-text">
        The company has no objection to the above-mentioned employee's application and confirms
        that they are in good standing with us.
    </p>
    @elseif($docType === 'experience_letter')
    <p class="body-text">
        This is to certify that <strong>{{ $employee?->name }}</strong> has been employed with
        our organization and has served with dedication and professionalism. This letter is issued
        upon their request for record purposes.
    </p>
    @elseif($docType === 'salary_certificate')
    <p class="body-text">
        This is to certify that <strong>{{ $employee?->name }}</strong>
        @if($employee?->employee_id ?? false)
            (Employee ID: <strong>{{ $employee->employee_id }}</strong>)
        @endif
        is currently employed with our organization. This salary certificate is issued upon the
        employee's request for <strong>{{ $purpose }}</strong>.
    </p>
    @endif

    <table class="info-table">
        <tr>
            <td>Employee Name</td>
            <td>{{ $employee?->name }}</td>
        </tr>
        @if($employee?->employee_id ?? false)
        <tr>
            <td>Employee ID</td>
            <td>{{ $employee->employee_id }}</td>
        </tr>
        @endif
        <tr>
            <td>Document Type</td>
            <td>{{ $docLabel }}</td>
        </tr>
        <tr>
            <td>Issued For</td>
            <td>{{ $purpose }}</td>
        </tr>
        <tr>
            <td>Issue Date</td>
            <td>{{ $issueDate }}</td>
        </tr>
        <tr>
            <td>Reference No</td>
            <td>{{ $refNo }}</td>
        </tr>
    </table>

    <p class="body-text">
        This certificate is issued in good faith and for the purpose stated above only.
    </p>

    <div class="signature-section">
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">Authorized Signatory<br>HR Department</div>
        </div>
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">Managing Director<br>epal ERP</div>
        </div>
    </div>

    <div class="seal-box">
        This is a system-generated document. &nbsp;·&nbsp; epal ERP &nbsp;·&nbsp; Ref: {{ $refNo }}
    </div>

</body>
</html>
