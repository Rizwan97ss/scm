<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $data['payslip']['payslip_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .subtitle { color: #555; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f2f2f2; }
        .text-right { text-align: right; }
        .summary { margin-top: 18px; width: 60%; }
        .summary td { border: none; padding: 3px 8px; }
        .summary td:first-child { color: #555; }
        .net { font-size: 16px; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <h1>{{ $schoolName }}</h1>
    <p class="subtitle">Payslip — {{ $data['payslip']['payslip_number'] }} — {{ sprintf('%02d', $data['payslip']['month']) }}/{{ $data['payslip']['year'] }}</p>

    <table class="summary" style="width:auto; margin-top:0;">
        <tr><td>Employee</td><td>{{ $data['employee']['full_name'] }}</td></tr>
        <tr><td>Employee ID</td><td>{{ $data['employee']['employee_id'] ?? '—' }}</td></tr>
        <tr><td>Status</td><td>{{ $data['payslip']['status'] }}</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Component</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Basic Salary</td><td class="text-right">{{ number_format($data['payslip']['basic_salary'], 2) }}</td></tr>
            <tr><td>Allowances</td><td class="text-right">{{ number_format($data['payslip']['allowances'], 2) }}</td></tr>
            <tr><td>Deductions</td><td class="text-right">-{{ number_format($data['payslip']['deductions'], 2) }}</td></tr>
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Net Salary</td><td class="net">{{ number_format($data['payslip']['net_salary'], 2) }}</td></tr>
        @if ($data['payslip']['paid_at'])
        <tr><td>Paid On</td><td>{{ $data['payslip']['paid_at'] }}</td></tr>
        @endif
    </table>

    <p class="footer">Generated {{ $generatedAt }}</p>
</body>
</html>
