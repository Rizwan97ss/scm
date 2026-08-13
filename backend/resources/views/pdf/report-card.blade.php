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
        .subject-block { margin-top: 14px; }
        .subject-header { font-weight: bold; font-size: 13px; margin-bottom: 3px; }
        .status-pending { color: #888; font-style: italic; }
        .components-table { margin-top: 0; }
        .components-table th, .components-table td { padding: 4px 8px; font-size: 11px; }
        .pass { color: #2a7d32; font-weight: bold; }
        .fail { color: #b3261e; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $schoolName }}</h1>
    <p class="subtitle">Report Card — {{ $data['exam']['name'] }}</p>

    <table class="summary" style="width:auto; margin-top:0;">
        <tr><td>Student</td><td>{{ $data['student']['full_name'] }}</td></tr>
        <tr><td>Admission #</td><td>{{ $data['student']['admission_number'] }}</td></tr>
    </table>

    @foreach ($data['subjects'] as $row)
        <div class="subject-block">
            <div class="subject-header">{{ $row['group']['subject']['name'] }}</div>

            @if ($row['group']['status'] !== 'published')
                <p class="status-pending">Result pending — not yet declared.</p>
            @else
                @if (count($row['components']) > 1)
                    <table class="components-table">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th class="text-right">Max</th>
                                <th class="text-right">Obtained</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($row['components'] as $component)
                                <tr>
                                    <td>{{ $component['type'] ?? '—' }}</td>
                                    <td class="text-right">{{ $component['max_marks'] }}</td>
                                    <td class="text-right">{{ $component['is_absent'] ? 'Absent' : ($component['marks_obtained'] ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <table>
                    <thead>
                        <tr>
                            <th class="text-right">Total Max</th>
                            <th class="text-right">Total Obtained</th>
                            <th class="text-right">Percentage</th>
                            <th>Grade</th>
                            <th>Pass/Fail</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-right">{{ $row['max_marks_total'] }}</td>
                            <td class="text-right">{{ $row['is_absent'] ? 'Absent' : ($row['marks_obtained_total'] ?? '—') }}</td>
                            <td class="text-right">{{ $row['percentage'] !== null ? $row['percentage'] . '%' : '—' }}</td>
                            <td>{{ $row['grade_label'] ?? '—' }}</td>
                            <td>
                                @if ($row['is_pass'] === true)
                                    <span class="pass">Pass</span>
                                @elseif ($row['is_pass'] === false)
                                    <span class="fail">Fail</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $row['remark'] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <table class="summary">
        <tr><td>Overall Percentage</td><td>{{ $data['overall_percentage'] !== null ? $data['overall_percentage'] . '%' : '—' }}</td></tr>
        <tr><td>Overall GPA</td><td>{{ $data['overall_gpa'] ?? '—' }}</td></tr>
    </table>

    <p class="footer">Generated {{ $generatedAt }}</p>
</body>
</html>
