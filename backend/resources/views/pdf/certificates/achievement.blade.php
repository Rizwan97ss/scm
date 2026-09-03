<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        @font-face { font-family: 'Playfair Display'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('PlayfairDisplay-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 600; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-SemiBold.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 700; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-Bold.ttf') }}) format('truetype'); }

        body { font-family: 'Poppins', sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 0; }
        .frame { margin: 22pt; border: 1.5pt solid #2a2a2a; padding: 24pt 60pt; text-align: center; }
        .ribbon { width: 40pt; margin-bottom: 6pt; }
        .school-name { font-size: 13px; color: #333333; margin-bottom: 10pt; }
        .title { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 26px; letter-spacing: 0.5px; text-transform: uppercase; line-height: 1.3; margin-bottom: 22pt; }
        .awarded-to { font-size: 12px; color: #333333; margin-bottom: 8pt; }
        .student-name { font-family: 'Playfair Display', serif; font-size: 34px; margin-bottom: 22pt; }
        .body-text { font-size: 11px; line-height: 1.7; max-width: 460pt; margin: 0 auto 30pt; color: #333333; }
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
