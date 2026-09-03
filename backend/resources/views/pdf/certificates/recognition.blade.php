<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        @page { size: 11in 8.5in landscape; margin: 0; }
        @font-face { font-family: 'Playfair Display'; font-weight: 700; src: url({{ \App\Support\FontEmbedder::dataUri('PlayfairDisplay-Bold.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 400; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-Regular.ttf') }}) format('truetype'); }
        @font-face { font-family: 'Poppins'; font-weight: 700; src: url({{ \App\Support\FontEmbedder::dataUri('Poppins-Bold.ttf') }}) format('truetype'); }

        body { font-family: 'Poppins', sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 54pt 70pt; text-align: center; }
        .school-name { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 24px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 30pt; }
        .title { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 34px; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 30pt; }
        .awarded-to { font-size: 13px; color: #333333; margin-bottom: 10pt; }
        .student-name { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 36px; color: #7ec0d6; margin-bottom: 24pt; }
        .body-text { font-size: 13px; line-height: 1.7; max-width: 440pt; margin: 0 auto 22pt; color: #262626; }
        .given { font-size: 12px; color: #333333; margin-bottom: 32pt; }
        .signature-line { width: 190pt; border-top: 1px solid #333333; margin: 0 auto 8pt; }
        .signature-name { font-weight: 700; font-size: 14px; }
        .signature-title { font-size: 12px; color: #4b4b4b; }
    </style>
</head>
<body>
    <div class="school-name">{{ $schoolName }}</div>
    <div class="title">Certificate of Recognition</div>
    <div class="awarded-to">This certificate is awarded to</div>
    <div class="student-name">{{ $certificate->student->full_name }}</div>
    <div class="body-text">{{ $certificate->content }}</div>
    <div class="given">Given this {{ $certificate->issued_date->format('F Y') }}.</div>

    @php($signatory = $signatories[0] ?? null)
    <div class="signature-line"></div>
    <div class="signature-name">{{ $signatory['name'] ?? $certificate->issuedBy->full_name }}</div>
    <div class="signature-title">{{ $signatory['title'] ?? 'School Principal' }}</div>
</body>
</html>
