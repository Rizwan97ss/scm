<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $student->full_name }} — ID Card</title>
    <style>
        /* See id-card-staff.blade.php's matching comment -- dompdf needs an
           exact @page size spelled out here too, and every element below is
           position:absolute against this exact 243x153 canvas rather than
           table/flow layout, for the same "guarantees one page regardless
           of content length" reasoning. */
        @page { size: 243pt 153pt; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1a1a1a; margin: 0; padding: 0; width: 243pt; height: 153pt; }
        .card { position: relative; width: 243pt; height: 153pt; border-radius: 6px; overflow: hidden; }
        /* The gradient itself is a real raster image (CardGradientGenerator),
           not CSS -- dompdf's linear-gradient background silently renders
           nothing on this installed version (confirmed directly: the page
           came back plain white). Painted first, everything else layers on
           top of it in DOM order. */
        .bg { position: absolute; top: 0; left: 0; width: 243pt; height: 153pt; }
        .abs { position: absolute; }
        .logo-badge { top: 9pt; left: 9pt; width: 18pt; height: 18pt; border-radius: 50%; background: #ffffff; text-align: center; overflow: hidden; }
        .logo-badge img { width: 18pt; height: 18pt; }
        .logo-badge .monogram { display: block; font-size: 10px; font-weight: bold; color: #1a1a1a; line-height: 18pt; }
        .school-name { top: 11pt; left: 31pt; width: 100pt; font-size: 7px; font-weight: bold; color: #ffffff; text-transform: uppercase; letter-spacing: 0.3px; line-height: 1.2; }
        .title { top: 10pt; left: 130pt; width: 104pt; font-size: 11px; font-weight: bold; color: #ffffff; text-align: right; line-height: 1.2; }
        .photo-ring { top: 40pt; left: 12pt; width: 54pt; height: 54pt; border-radius: 50%; background: #ffffff; padding: 2pt; }
        .photo { width: 50pt; height: 50pt; border-radius: 50%; overflow: hidden; background: #eef2f7; text-align: center; }
        .photo img { width: 50pt; height: 50pt; }
        .initials { display: block; font-size: 16px; font-weight: bold; color: #94a3b8; line-height: 50pt; }
        .barcode-panel { top: 100pt; left: 8pt; width: 62pt; height: 44pt; background: #ffffff; border-radius: 4pt; text-align: center; padding-top: 3pt; }
        .barcode-panel img { width: 56pt; height: 24pt; }
        .barcode-value { font-size: 6px; color: #555555; margin-top: 2pt; letter-spacing: 0.3px; }
        .info-panel { top: 34pt; left: 82pt; width: 152pt; height: 112pt; background: rgba(255,255,255,0.94); border-radius: 6pt; }
        .info-row { left: 92pt; width: 132pt; height: 20pt; }
        .info-label { font-size: 6.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4px; }
        .info-value { font-size: 9px; font-weight: bold; color: #16213e; line-height: 1.2; }
    </style>
</head>
<body>
    <div class="card">
        <img class="bg abs" src="{{ $background }}" alt="">

        <div class="abs logo-badge">
            @if($logo)
                <img src="{{ $logo }}" alt="">
            @else
                <span class="monogram">{{ strtoupper(substr($schoolName, 0, 1)) }}</span>
            @endif
        </div>
        <div class="abs school-name">{{ $schoolName }}</div>

        <div class="abs title">STUDENT<br>ID CARD</div>

        <div class="abs photo-ring">
            <div class="photo">
                @if($photo)
                    <img src="{{ $photo }}" alt="">
                @else
                    <span class="initials">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</span>
                @endif
            </div>
        </div>

        <div class="abs barcode-panel">
            @if($barcode)
                <img src="{{ $barcode }}" alt="">
            @endif
            <div class="barcode-value">{{ $student->admission_number }}</div>
        </div>

        <div class="abs info-panel"></div>
        {{-- School Admin-configurable which of these appear (Settings > ID Cards) -- IdCardController only
             sends the rows that should show, so a hidden one leaves no gap for the rest to shift into. --}}
        @foreach($infoRows as $index => $row)
            <div class="abs info-row" style="top: {{ 42 + $index * 22 }}pt;">
                <div class="info-label">{{ $row['label'] }}</div>
                <div class="info-value">{{ $row['value'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
