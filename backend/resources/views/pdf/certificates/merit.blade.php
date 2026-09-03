<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        @font-face { font-family: 'Playfair Display'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('PlayfairDisplay-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Playfair Display'; font-weight: 600; src: url({{ \App\Support\FontEmbedder::dataUri('PlayfairDisplay-SemiBold.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Great Vibes'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('GreatVibes-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-Regular.ttf') }}) format('truetype'); }

        body { font-family: 'Poppins', sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 0; }
        .canvas { position: relative; padding: 34pt 70pt; text-align: center; }
        .corner { position: absolute; width: 34pt; height: 34pt; border: 1.4pt solid #c9a24b; }
        .corner-tl { top: 18pt; left: 18pt; border-right: none; border-bottom: none; }
        .corner-tr { top: 18pt; right: 18pt; border-left: none; border-bottom: none; }
        .corner-bl { bottom: 18pt; left: 18pt; border-right: none; border-top: none; }
        .corner-br { bottom: 18pt; right: 18pt; border-left: none; border-top: none; }
        .medal { width: 38pt; margin-bottom: 6pt; }
        .school-name { font-size: 12px; color: #333333; margin-bottom: 10pt; }
        .title { font-family: 'Playfair Display', serif; font-weight: 600; font-size: 30px; color: #b8862f; margin-bottom: 18pt; }
        .awarded-to { font-size: 11px; line-height: 1.6; max-width: 440pt; margin: 0 auto 20pt; color: #333333; }
        .student-name { font-family: 'Great Vibes', cursive; font-size: 46px; color: #b8862f; margin-bottom: 20pt; }
        .body-text { font-size: 10.5px; line-height: 1.7; max-width: 480pt; margin: 0 auto 30pt; color: #333333; }
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
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>

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
