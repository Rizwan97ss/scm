<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; }
        .border { border: 6px double #2a4d8f; padding: 40px; text-align: center; }
        h1 { font-size: 22px; margin-bottom: 4px; color: #2a4d8f; }
        .subtitle { color: #555; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 2px; font-size: 12px; }
        .type { font-size: 16px; font-weight: bold; margin-bottom: 20px; }
        .content { font-size: 15px; line-height: 1.8; margin: 0 auto; max-width: 80%; }
        .meta-table { margin-top: 40px; font-size: 11px; color: #555; }
        .footer { margin-top: 40px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <div class="border">
        <h1>{{ $schoolName }}</h1>
        <p class="subtitle">Certificate No. {{ $certificate->certificate_number }}</p>
        <p class="type">{{ $certificate->certificateTemplate->type }}</p>
        <p class="content">{{ $certificate->content }}</p>
        <table class="meta-table" style="width:100%;">
            <tr>
                <td style="text-align:left;">Issued: {{ $certificate->issued_date->toFormattedDateString() }}</td>
                <td style="text-align:right;">Issued by: {{ $certificate->issuedBy->full_name }}</td>
            </tr>
        </table>
    </div>
    <p class="footer">Generated {{ $generatedAt }}</p>
</body>
</html>
