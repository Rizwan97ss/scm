<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $staff->full_name }} — ID Card</title>
    <style>
        /* DomPDF applies its own default @page margin regardless of body's
           margin — on a page this small (153pt tall) that default alone
           exceeds the whole page, which is what was actually forcing this
           onto a second page. size must be spelled out here too, matching
           IdCardController's setPaper([0, 0, 243, 153]) exactly — an @page
           rule with only `margin` and no `size` makes dompdf's CSS engine
           take over sizing and silently fall back to A4, overriding the
           controller's setPaper() call (the card rendered correctly at
           1 page, but as a tiny card adrift on a full A4 page). */
        @page { size: 243pt 153pt; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1a1a1a; margin: 0; padding: 6px; }
        .card { border: 1.5px solid #2a4d8f; border-radius: 6px; padding: 6px; width: 100%; height: 137px; }
        .header { text-align: center; font-size: 9px; font-weight: bold; color: #2a4d8f; margin-bottom: 4px; }
        table.body { width: 100%; border-collapse: collapse; }
        table.body td { vertical-align: top; padding: 0; }
        .qr { width: 52px; text-align: center; }
        .qr img { width: 48px; height: 48px; }
        .details { padding-left: 6px; }
        .details p { margin: 1.5px 0; line-height: 1.25; }
        .label { color: #666; }
        .name { font-size: 10px; font-weight: bold; margin-bottom: 3px !important; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">{{ $schoolName }} — STAFF ID</div>
        <table class="body">
            <tr>
                <td class="qr"><img src="{{ $qrCode }}" alt="QR"></td>
                <td class="details">
                    <p class="name">{{ $staff->full_name }}</p>
                    <p><span class="label">Employee #:</span> {{ $staff->employee_id ?? '—' }}</p>
                    <p><span class="label">Designation:</span> {{ $staff->designation?->name ?? '—' }}</p>
                    <p><span class="label">Phone:</span> {{ $staff->phone ?? '—' }}</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
