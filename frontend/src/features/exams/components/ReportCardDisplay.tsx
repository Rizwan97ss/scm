import { Download } from 'lucide-react'
import { Badge, Button, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { downloadFile } from '@/utils/download'
import type { ReportCard } from '@/types/exam'

export function ReportCardDisplay({ report, pdfUrl }: { report: ReportCard; pdfUrl?: string }) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="font-semibold">{report.exam.name}</h3>
          {!report.exam.is_published && <Badge variant="warning">Not yet published</Badge>}
        </div>
        {pdfUrl && (
          <Button variant="outline" onClick={() => downloadFile(pdfUrl, `${report.student.full_name}-${report.exam.name}-report-card.pdf`)}>
            <Download className="h-4 w-4" /> Download PDF
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <StatCard label="Overall Percentage" value={report.overall_percentage !== null ? `${report.overall_percentage}%` : '—'} />
        <StatCard label="Overall GPA" value={report.overall_gpa ?? '—'} />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Subject</TableHead>
            <TableHead>Max Marks</TableHead>
            <TableHead>Obtained</TableHead>
            <TableHead>Percentage</TableHead>
            <TableHead>Grade</TableHead>
            <TableHead>Remark</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {report.subjects.length === 0 && (
            <TableRow><TableCell colSpan={6} className="text-center text-sm text-muted-foreground">No marks entered yet.</TableCell></TableRow>
          )}
          {report.subjects.map((row) => (
            <TableRow key={row.subject.id}>
              <TableCell className="font-medium">{row.subject.name}</TableCell>
              <TableCell>{row.max_marks}</TableCell>
              <TableCell>{row.is_absent ? <Badge variant="destructive">Absent</Badge> : (row.marks_obtained ?? '—')}</TableCell>
              <TableCell>{row.percentage !== null ? `${row.percentage}%` : '—'}</TableCell>
              <TableCell>{row.grade_label ?? '—'}</TableCell>
              <TableCell className="text-muted-foreground">{row.remark ?? row.remarks ?? '—'}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
