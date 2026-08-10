<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $staff->full_name }} — ID Card</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; margin: 0; }
        .card { border: 1.5px solid #2a4d8f; border-radius: 8px; padding: 8px; height: 133px; box-sizing: border-box; }
        .header { text-align: center; font-size: 10px; font-weight: bold; color: #2a4d8f; margin-bottom: 4px; }
        .body { display: flex; }
        .qr { width: 55px; text-align: center; }
        .qr img { width: 50px; height: 50px; }
        .details { flex: 1; padding-left: 8px; }
        .details p { margin: 2px 0; }
        .label { color: #666; }
        .name { font-size: 12px; font-weight: bold; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">{{ $schoolName }} — STAFF ID</div>
        <div class="body">
            <div class="qr"><img src="{{ $qrCode }}" alt="QR"></div>
            <div class="details">
                <p class="name">{{ $staff->full_name }}</p>
                <p><span class="label">Employee #:</span> {{ $staff->employee_id ?? '—' }}</p>
                <p><span class="label">Designation:</span> {{ $staff->designation?->name ?? '—' }}</p>
                <p><span class="label">Phone:</span> {{ $staff->phone ?? '—' }}</p>
            </div>
        </div>
    </div>
</body>
</html>
