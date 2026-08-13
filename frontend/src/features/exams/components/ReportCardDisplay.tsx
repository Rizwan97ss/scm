import { Download } from 'lucide-react'
import { Badge, Button, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { downloadFile } from '@/utils/download'
import type { BadgeVariant } from '@/components/ui/Badge'
import type { ReportCard, SubjectResultStatus } from '@/types/exam'

const STATUS_LABELS: Record<SubjectResultStatus, string> = { draft: 'Draft', calculated: 'Calculated', published: 'Published' }
const STATUS_VARIANTS: Record<SubjectResultStatus, BadgeVariant> = { draft: 'default', calculated: 'warning', published: 'success' }

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
            <TableHead>Components</TableHead>
            <TableHead>Total</TableHead>
            <TableHead>Percentage</TableHead>
            <TableHead>Grade</TableHead>
            <TableHead>Pass/Fail</TableHead>
            <TableHead>Status</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {report.subjects.length === 0 && (
            <TableRow><TableCell colSpan={7} className="text-center text-sm text-muted-foreground">No subjects configured yet.</TableCell></TableRow>
          )}
          {report.subjects.map((row) => (
            <TableRow key={row.group.id}>
              <TableCell className="font-medium">{row.group.subject.name}</TableCell>
              <TableCell className="text-sm text-muted-foreground">
                {row.components.length > 0
                  ? row.components.map((c) => `${c.type ?? 'Component'}: ${c.is_absent ? 'Absent' : (c.marks_obtained ?? '—')}/${c.max_marks}`).join(', ')
                  : '—'}
              </TableCell>
              <TableCell>{row.is_absent ? <Badge variant="destructive">Absent</Badge> : `${row.marks_obtained_total ?? '—'} / ${row.max_marks_total}`}</TableCell>
              <TableCell>{row.percentage !== null ? `${row.percentage}%` : '—'}</TableCell>
              <TableCell>{row.grade_label ?? '—'}</TableCell>
              <TableCell>
                {row.is_pass === true && <Badge variant="success">Pass</Badge>}
                {row.is_pass === false && <Badge variant="destructive">Fail</Badge>}
                {row.is_pass === null && '—'}
              </TableCell>
              <TableCell>
                <Badge variant={STATUS_VARIANTS[row.group.status]}>{STATUS_LABELS[row.group.status]}</Badge>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
