<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        @page { size: 11in 8.5in landscape; margin: 0; }
        @font-face { font-family: 'Playfair Display'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('PlayfairDisplay-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Playfair Display'; font-weight: 600; src: url({{ \App\Support\FontEmbedder::dataUri('PlayfairDisplay-SemiBold.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Great Vibes'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('GreatVibes-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-Regular.ttf') }}) format('truetype'); }

        body { font-family: 'Poppins', sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 0; }
        .canvas { position: relative; padding: 40pt 76pt; text-align: center; }
        .corner { position: absolute; width: 40pt; height: 40pt; border: 1.2pt solid #c9a24b; }
        .corner-tl { top: 20pt; left: 20pt; border-right: none; border-bottom: none; }
        .corner-tl-inner { top: 26pt; left: 26pt; border-right: none; border-bottom: none; }
        .corner-tr { top: 20pt; right: 20pt; border-left: none; border-bottom: none; }
        .corner-tr-inner { top: 26pt; right: 26pt; border-left: none; border-bottom: none; }
        .corner-bl { bottom: 20pt; left: 20pt; border-right: none; border-top: none; }
        .corner-bl-inner { bottom: 26pt; left: 26pt; border-right: none; border-top: none; }
        .corner-br { bottom: 20pt; right: 20pt; border-left: none; border-top: none; }
        .corner-br-inner { bottom: 26pt; right: 26pt; border-left: none; border-top: none; }
        .medal { width: 42pt; margin-bottom: 8pt; }
        .school-name { font-size: 13px; color: #333333; margin-bottom: 12pt; }
        .title { font-family: 'Playfair Display', serif; font-weight: 600; font-size: 34px; color: #b8862f; margin-bottom: 22pt; }
        .awarded-to { font-size: 12px; line-height: 1.6; max-width: 460pt; margin: 0 auto 24pt; color: #333333; }
        .student-name { font-family: 'Great Vibes', cursive; font-size: 52px; color: #b8862f; margin-bottom: 24pt; }
        .body-text { font-size: 11px; line-height: 1.7; max-width: 500pt; margin: 0 auto 34pt; color: #333333; }
        .sign-row { width: 100%; }
        .sign-cell { width: 33%; vertical-align: bottom; }
        .sign-name { font-weight: 700; font-size: 12px; border-top: 1px solid #333333; padding-top: 6pt; display: inline-block; min-width: 140pt; }
        .sign-title { font-size: 10px; color: #4b4b4b; margin-top: 2pt; }
        .verify-cell { text-align: center; }
        .verify-cell img { width: 46pt; height: 46pt; }
        .badge-label { font-size: 8px; font-weight: 700; letter-spacing: 1px; color: #2f9e44; margin-top: 4pt; }
    </style>
</head>
<body>
    <div class="canvas">
        <div class="corner corner-tl"></div>
        <div class="corner corner-tl-inner"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-tr-inner"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-bl-inner"></div>
        <div class="corner corner-br"></div>
        <div class="corner corner-br-inner"></div>

        <img class="medal" src="{{ \App\Support\MedalGenerator::dataUri('#c9a24b', true, 90) }}" alt="">

        <div class="school-name">{{ $schoolName }}</div>
        <div class="title">Certificate of Merit</div>
        <div class="awarded-to">This certificate is proudly presented as a mark of excellence, creativity, hard work and commitment to</div>
        <div class="student-name">{{ $certificate->student->full_name }}</div>
        <div class="body-text">{{ $certificate->content }}</div>

        @php($first = $signatories[0] ?? null)
        @php($second = $signatories[1] ?? null)
        <table class="sign-row" cellpadding="0" cellspacing="0">
            <tr>
                <td class="sign-cell" style="text-align: left;">
                    <div class="sign-name">{{ $first['name'] ?? ' ' }}</div>
                    <div class="sign-title">{{ $first['title'] ?? '' }}</div>
                </td>
                <td class="sign-cell verify-cell">
                    @if($qrCodeDataUri)
                        <img src="{{ $qrCodeDataUri }}" alt="">
                        <div class="badge-label">CERTIFIED</div>
                    @endif
                </td>
                <td class="sign-cell" style="text-align: right;">
                    <div class="sign-name">{{ $second['name'] ?? ' ' }}</div>
                    <div class="sign-title">{{ $second['title'] ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
