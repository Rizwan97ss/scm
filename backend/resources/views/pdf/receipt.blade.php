<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt — {{ $data['payment']['payment_number'] }}</title>
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
        .amount { font-size: 16px; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <h1>{{ $schoolName }}</h1>
    <p class="subtitle">Payment Receipt — {{ $data['payment']['payment_number'] }}</p>

    <table class="summary" style="width:auto; margin-top:0;">
        <tr><td>Student</td><td>{{ $data['student']['full_name'] }}</td></tr>
        <tr><td>Admission #</td><td>{{ $data['student']['admission_number'] }}</td></tr>
        <tr><td>Invoice #</td><td>{{ $data['invoice']['invoice_number'] }}</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Date Paid</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $data['payment']['paid_at'] }}</td>
                <td>{{ $data['payment']['method'] }}</td>
                <td>{{ $data['payment']['reference_number'] ?? '—' }}</td>
                <td class="text-right amount">{{ number_format($data['payment']['amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Invoice Total</td><td>{{ number_format($data['invoice']['total'], 2) }}</td></tr>
        <tr><td>Remaining Balance</td><td>{{ number_format($data['invoice']['balance'], 2) }}</td></tr>
        <tr><td>Received By</td><td>{{ $data['received_by'] }}</td></tr>
    </table>

    <p class="footer">Generated {{ $generatedAt }}</p>
</body>
</html>
