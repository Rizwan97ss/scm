import { Download } from 'lucide-react'
import { Button, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { downloadFile } from '@/utils/download'
import type { TermResult } from '@/types/exam'

export function TermResultDisplay({ result, pdfUrl }: { result: TermResult; pdfUrl?: string }) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h3 className="font-semibold">{result.term.name} — Consolidated Result</h3>
        {pdfUrl && (
          <Button variant="outline" onClick={() => downloadFile(pdfUrl, `${result.student.full_name}-${result.term.name}-term-result.pdf`)}>
            <Download className="h-4 w-4" /> Download PDF
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <StatCard label="Weighted Percentage" value={result.weighted_percentage !== null ? `${result.weighted_percentage}%` : '—'} />
        <StatCard label="Weighted GPA" value={result.weighted_gpa ?? '—'} />
        <StatCard label="Grade" value={result.grade_label ?? '—'} />
        <StatCard label="Rank" value={result.rank ? `${result.rank.position} / ${result.rank.out_of}` : '—'} />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Exam</TableHead>
            <TableHead>Weight</TableHead>
            <TableHead>Percentage</TableHead>
            <TableHead>GPA</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {result.exams.length === 0 && (
            <TableRow><TableCell colSpan={4} className="text-center text-sm text-muted-foreground">No published exams contribute to this term yet.</TableCell></TableRow>
          )}
          {result.exams.map((row) => (
            <TableRow key={row.exam.id}>
              <TableCell className="font-medium">{row.exam.name}</TableCell>
              <TableCell>{row.weight}</TableCell>
              <TableCell>{row.percentage !== null ? `${row.percentage}%` : '—'}</TableCell>
              <TableCell>{row.gpa ?? '—'}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
