<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Term Result — {{ $data['student']['full_name'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .subtitle { color: #555; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f2f2f2; }
        .text-right { text-align: right; }
        .summary { margin-top: 18px; width: 50%; }
        .summary td { border: none; padding: 3px 8px; }
        .summary td:first-child { color: #555; }
        .footer { margin-top: 40px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <h1>{{ $schoolName }}</h1>
    <p class="subtitle">Consolidated Term Result — {{ $data['term']['name'] }}</p>

    <table class="summary" style="width:auto; margin-top:0;">
        <tr><td>Student</td><td>{{ $data['student']['full_name'] }}</td></tr>
        <tr><td>Admission #</td><td>{{ $data['student']['admission_number'] }}</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Exam</th>
                <th class="text-right">Weight</th>
                <th class="text-right">Percentage</th>
                <th class="text-right">GPA</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['exams'] as $row)
                <tr>
                    <td>{{ $row['exam']['name'] }}</td>
                    <td class="text-right">{{ $row['weight'] }}</td>
                    <td class="text-right">{{ $row['percentage'] !== null ? $row['percentage'] . '%' : '—' }}</td>
                    <td class="text-right">{{ $row['gpa'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Weighted Percentage</td><td>{{ $data['weighted_percentage'] !== null ? $data['weighted_percentage'] . '%' : '—' }}</td></tr>
        <tr><td>Weighted GPA</td><td>{{ $data['weighted_gpa'] ?? '—' }}</td></tr>
        <tr><td>Grade</td><td>{{ $data['grade_label'] ?? '—' }}</td></tr>
        @if ($data['rank'])
            <tr><td>Rank</td><td>{{ $data['rank']['position'] }} of {{ $data['rank']['out_of'] }}</td></tr>
        @endif
    </table>

    <p class="footer">Generated {{ $generatedAt }}</p>
</body>
</html>
