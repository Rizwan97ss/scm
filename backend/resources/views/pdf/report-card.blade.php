<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report Card — {{ $data['student']['full_name'] }}</title>
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
    <p class="subtitle">Report Card — {{ $data['exam']['name'] }}</p>

    <table class="summary" style="width:auto; margin-top:0;">
        <tr><td>Student</td><td>{{ $data['student']['full_name'] }}</td></tr>
        <tr><td>Admission #</td><td>{{ $data['student']['admission_number'] }}</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Subject</th>
                <th class="text-right">Max Marks</th>
                <th class="text-right">Obtained</th>
                <th class="text-right">Percentage</th>
                <th>Grade</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['subjects'] as $row)
                <tr>
                    <td>{{ $row['subject']['name'] }}</td>
                    <td class="text-right">{{ $row['max_marks'] }}</td>
                    <td class="text-right">{{ $row['is_absent'] ? 'Absent' : ($row['marks_obtained'] ?? '—') }}</td>
                    <td class="text-right">{{ $row['percentage'] !== null ? $row['percentage'] . '%' : '—' }}</td>
                    <td>{{ $row['grade_label'] ?? '—' }}</td>
                    <td>{{ $row['remark'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Overall Percentage</td><td>{{ $data['overall_percentage'] !== null ? $data['overall_percentage'] . '%' : '—' }}</td></tr>
        <tr><td>Overall GPA</td><td>{{ $data['overall_gpa'] ?? '—' }}</td></tr>
    </table>

    <p class="footer">Generated {{ $generatedAt }}</p>
</body>
</html>
