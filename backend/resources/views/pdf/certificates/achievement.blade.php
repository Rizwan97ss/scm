<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        @page { size: 11in 8.5in landscape; margin: 0; }
        @font-face { font-family: 'Playfair Display'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('PlayfairDisplay-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 600; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-SemiBold.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 700; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-Bold.ttf') }}) format('truetype'); }

        body { font-family: 'Poppins', sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 0; position: relative; }
        .bg-square { position: absolute; width: 130pt; height: 130pt; background: #f1f1f1; }
        .bg-square.tl { top: 0; left: 0; }
        .bg-square.tr { top: 0; right: 0; }
        .bg-square.bl { bottom: 0; left: 0; }
        .bg-square.br { bottom: 0; right: 0; }
        .frame { position: relative; margin: 22pt; border: 1.5pt solid #2a2a2a; padding: 26pt 60pt; text-align: center; background: #ffffff; }
        .ribbon { width: 46pt; margin-bottom: 8pt; }
        .school-name { font-size: 14px; color: #333333; margin-bottom: 12pt; }
        .title { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 32px; letter-spacing: 0.5px; text-transform: uppercase; line-height: 1.3; margin-bottom: 26pt; }
        .awarded-to { font-size: 13px; color: #333333; margin-bottom: 10pt; }
        .student-name { font-family: 'Playfair Display', serif; font-size: 42px; margin-bottom: 26pt; }
        .body-text { font-size: 12px; line-height: 1.7; max-width: 480pt; margin: 0 auto 34pt; color: #333333; }
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
    <div class="bg-square tl"></div>
    <div class="bg-square tr"></div>
    <div class="bg-square bl"></div>
    <div class="bg-square br"></div>
    <div class="frame">
        <img class="ribbon" src="{{ \App\Support\MedalGenerator::dataUri('#1a1a1a', false, 90) }}" alt="">

        <div class="school-name">{{ $schoolName }}</div>
        <div class="title">Certificate of<br>Achievement</div>
        <div class="awarded-to">This certificate is presented to</div>
        <div class="student-name">{{ $certificate->student->full_name }}</div>
        <div class="body-text">{{ $certificate->content }}</div>

        @php($first = $signatories[0] ?? null)
        @php($second = $signatories[1] ?? null)
        <table class="sign-row" cellpadding="0" cellspacing="0">
            <tr>
                <td class="sign-cell" style="text-align: left;">
                    <div class="sign-name">{{ $first['name'] ?? ' ' }}</div>
                    <div class="sign-title">{{ $first['title'] ?? '' }}</div>
                </td>
                <td class="sign-cell verify-cell">
                    @if($qrCodeDataUri)
                        <img src="{{ $qrCodeDataUri }}" alt="">
                        <div class="badge-label">CERTIFIED</div>
                    @endif
                </td>
                <td class="sign-cell" style="text-align: right;">
                    <div class="sign-name">{{ $second['name'] ?? ' ' }}</div>
                    <div class="sign-title">{{ $second['title'] ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
