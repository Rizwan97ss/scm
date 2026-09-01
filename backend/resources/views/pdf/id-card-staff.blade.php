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
           1 page, but as a tiny card adrift on a full A4 page).

           Every element below is position:absolute with an explicit top/
           left/width against this exact 243x153 canvas, not table/flow
           layout — dompdf's content-driven row/table height doesn't
           reliably stay within an explicit @page size (a wrapped name or
           a hair of line-height overflow silently pushes a second, mostly
           blank page), so pinning every box to a fixed coordinate is what
           actually guarantees this stays one page regardless of content
           length. */
        @page { size: 243pt 153pt; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1a1a1a; margin: 0; padding: 0; width: 243pt; height: 153pt; }
        /* box-shadow, not border — see id-card-student's matching note: a
           border participates in border-box width even at 1px, enough to
           push a right-edge-flush element onto a second, overflow page. */
        .card { position: relative; width: 243pt; height: 153pt; border-radius: 6px; overflow: hidden; box-shadow: inset 0 0 0 1px #d5d5d5; background: #ffffff; }
        .abs { position: absolute; }
        /* Header band -- a plain solid background-color (unlike the student
           card's gradient), which dompdf renders correctly and reliably;
           see CardGradientGenerator's docblock for why a gradient here
           would instead need to be a raster image. */
        .header { top: 0; left: 0; width: 243pt; height: 38pt; background: {{ $accentColor }}; }
        .logo-badge { top: 8pt; left: 9pt; width: 20pt; height: 20pt; border-radius: 50%; background: #ffffff; text-align: center; overflow: hidden; }
        .logo-badge img { width: 20pt; height: 20pt; }
        .logo-badge .monogram { display: block; font-size: 11px; font-weight: bold; color: #1a1a1a; line-height: 20pt; }
        .org-name { top: 9pt; left: 35pt; width: 195pt; font-size: 9px; font-weight: bold; color: #ffffff; text-transform: uppercase; letter-spacing: 0.4px; line-height: 1.2; }
        .org-subtitle { top: 21pt; left: 35pt; width: 195pt; font-size: 6px; color: rgba(255,255,255,0.85); text-transform: uppercase; letter-spacing: 0.6px; }
        .photo { top: 46pt; left: 9pt; width: 42pt; height: 50pt; border: 1px solid #d9dde3; border-radius: 3pt; overflow: hidden; background: #f1f4f8; text-align: center; }
        .photo img { width: 42pt; height: 50pt; }
        .initials { display: block; font-size: 15px; font-weight: bold; color: #94a3b8; line-height: 50pt; }
        .name { top: 46pt; left: 58pt; width: 176pt; font-size: 11px; font-weight: bold; color: #16213e; line-height: 1.2; }
        .role { top: 58pt; left: 58pt; width: 176pt; font-size: 7.5px; color: {{ $accentColor }}; font-weight: bold; }
        .contact { left: 58pt; width: 176pt; font-size: 7px; color: #333333; line-height: 1.3; }
        .barcode-panel { top: 114pt; left: 9pt; width: 66pt; text-align: left; }
        .barcode-panel img { width: 62pt; height: 22pt; }
        .barcode-id-label { font-size: 6px; color: #888888; text-transform: uppercase; letter-spacing: 0.4px; margin-top: 1pt; }
        .barcode-id-value { font-size: 8px; font-weight: bold; color: #16213e; }
        .footer-right { top: 118pt; left: 150pt; width: 84pt; text-align: right; }
        .valid-label { font-size: 6px; color: #888888; text-transform: uppercase; letter-spacing: 0.3px; }
        .valid-value { font-size: 7.5px; font-weight: bold; color: #16213e; }
        .watermark { top: 138pt; left: 214pt; width: 20pt; height: 20pt; border-radius: 50%; overflow: hidden; opacity: 0.9; text-align: center; }
        .watermark img { width: 20pt; height: 20pt; }
    </style>
</head>
<body>
    <div class="card">
        <div class="abs header"></div>
        <div class="abs logo-badge">
            @if($logo)
                <img src="{{ $logo }}" alt="">
            @else
                <span class="monogram">{{ strtoupper(substr($schoolName, 0, 1)) }}</span>
            @endif
        </div>
        <div class="abs org-name">{{ $schoolName }}</div>
        <div class="abs org-subtitle">Official Staff Identification</div>

        <div class="abs photo">
            @if($photo)
                <img src="{{ $photo }}" alt="">
            @else
                <span class="initials">{{ strtoupper(substr($staff->first_name, 0, 1).substr($staff->last_name, 0, 1)) }}</span>
            @endif
        </div>

        <div class="abs name">{{ $staff->full_name }}</div>
        <div class="abs role">{{ $staff->designation?->name ?? '—' }}</div>

        {{-- School Admin-configurable which contact rows appear (Settings > ID Cards) -- IdCardController
             only sends the rows that should show, so a hidden one leaves no gap for the rest to shift into. --}}
        @foreach($contactRows as $index => $row)
            <div class="abs contact" style="top: {{ 72 + $index * 10 }}pt;">{!! $row['icon'] !!}&nbsp; {{ $row['value'] }}</div>
        @endforeach

        <div class="abs barcode-panel">
            @if($barcode)
                <img src="{{ $barcode }}" alt="">
            @endif
            <div class="barcode-id-label">Employee ID</div>
            <div class="barcode-id-value">{{ $staff->employee_id ?? '—' }}</div>
        </div>

        <div class="abs footer-right">
            <div class="valid-label">Issued</div>
            <div class="valid-value">{{ $staff->hire_date?->toDateString() ?? now()->toDateString() }}</div>
        </div>
        @if($logo)
            <div class="abs watermark"><img src="{{ $logo }}" alt=""></div>
        @endif
    </div>
</body>
</html>
