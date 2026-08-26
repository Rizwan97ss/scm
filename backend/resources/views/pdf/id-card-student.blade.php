<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $student->full_name }} — ID Card</title>
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
        /* box-shadow, not border — a border participates in the box-sizing:
           border-box width even at 1px, and that alone was enough to push
           the side strip (whose right edge sits exactly at the card's
           243pt width) onto a second, overflow page. box-shadow paints
           without consuming any box-model space. */
        .card { position: relative; width: 243pt; height: 153pt; border-radius: 6px; overflow: hidden; box-shadow: inset 0 0 0 1px #d5d5d5; }
        .abs { position: absolute; }
        .brand { top: 8pt; left: 9pt; width: 160pt; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .name { top: 20pt; left: 9pt; width: 160pt; font-size: 10px; font-weight: bold; line-height: 1.15; max-height: 22pt; overflow: hidden; }
        .meta-grade { top: 44pt; left: 9pt; width: 160pt; font-size: 7px; color: #555555; }
        .meta-dob { top: 53pt; left: 9pt; width: 160pt; font-size: 7px; color: #555555; }
        .photo { top: 8pt; left: 176pt; width: 40pt; height: 46pt; border: 1px solid #cccccc; border-radius: 3px; overflow: hidden; background: #f1f1f1; text-align: center; }
        .photo img { width: 40pt; height: 46pt; }
        .initials { display: block; font-size: 15px; font-weight: bold; color: #999999; line-height: 46pt; }
        .qr { top: 112pt; left: 9pt; width: 26pt; height: 26pt; }
        .id-label { top: 112pt; left: 41pt; width: 130pt; font-size: 6.5px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px; }
        .id-value { top: 120pt; left: 41pt; width: 130pt; font-size: 9px; font-weight: bold; }
        .issued { top: 131pt; left: 41pt; width: 130pt; font-size: 6.5px; color: #888888; }
        /* Colored badge strip along the right edge — the accent color is
           the school's own branding.primary_color setting, fetched
           server-side (IdCardController), so this reflects each school's
           actual brand instead of a hardcoded color. dompdf's CSS
           transform/writing-mode support is too unreliable to rotate a
           single text run here (it silently dropped the label and threw
           the page count off instead) — one letter per line is a plain
           block technique that always renders correctly. */
        .side { top: 0; left: 224pt; width: 18pt; height: 153pt; background: {{ $accentColor }}; text-align: center; }
        .side-text { top: 54pt; left: 0; width: 18pt; color: #ffffff; font-size: 7px; font-weight: bold; line-height: 1.4; }
    </style>
</head>
<body>
    <div class="card">
        <div class="abs brand">{{ $schoolName }}</div>
        <div class="abs name">{{ $student->full_name }}</div>
        <div class="abs meta-grade">{{ $student->currentGradeLevel?->name ?? '—' }}{{ $student->currentSection ? ' - '.$student->currentSection->name : '' }}</div>
        <div class="abs meta-dob">DOB: {{ $student->date_of_birth?->toDateString() ?? '—' }}</div>
        <div class="abs photo">
            @if($photo)
                <img src="{{ $photo }}" alt="">
            @else
                <span class="initials">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</span>
            @endif
        </div>
        <img class="abs qr" src="{{ $qrCode }}" alt="">
        <div class="abs id-label">Student ID</div>
        <div class="abs id-value">{{ $student->admission_number }}</div>
        <div class="abs issued">Issued: {{ $student->admission_date?->toDateString() ?? now()->toDateString() }}</div>
        <div class="abs side">
            <div class="abs side-text">@foreach(str_split('STUDENT') as $letter){{ $letter }}<br>@endforeach</div>
        </div>
    </div>
</body>
</html>
