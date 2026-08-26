<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $announcement->title }}</title>
</head>
<body style="font-family: DejaVu Sans, Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Hi {{ $recipient->first_name }},</p>

    <h2 style="margin-bottom: 4px;">{{ $announcement->title }}</h2>
    <p style="white-space: pre-line;">{{ $announcement->body }}</p>

    <p style="margin-top: 24px; font-size: 12px; color: #888;">
        This is an automated message from {{ $recipient->school->name }}.
    </p>
</body>
</html>
