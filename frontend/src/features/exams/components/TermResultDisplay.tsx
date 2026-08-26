import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { Button, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { downloadFile } from '@/utils/download'
import type { TermResult } from '@/types/exam'

export function TermResultDisplay({ result, pdfUrl }: { result: TermResult; pdfUrl?: string }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h3 className="font-semibold">{t('exams.consolidatedResultHeading', { term: result.term.name })}</h3>
        {pdfUrl && (
          <Button variant="outline" onClick={() => downloadFile(pdfUrl, `${result.student.full_name}-${result.term.name}-term-result.pdf`)}>
            <Download className="h-4 w-4" /> {t('exams.downloadPdf')}
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <StatCard label={t('exams.weightedPercentage')} value={result.weighted_percentage !== null ? `${result.weighted_percentage}%` : '—'} />
        <StatCard label={t('exams.weightedGpa')} value={result.weighted_gpa ?? '—'} />
        <StatCard label={t('exams.grade')} value={result.grade_label ?? '—'} />
        <StatCard label={t('exams.rank')} value={result.rank ? `${result.rank.position} / ${result.rank.out_of}` : '—'} />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('entities.exam')}</TableHead>
            <TableHead>{t('exams.weight')}</TableHead>
            <TableHead>{t('exams.percentage')}</TableHead>
            <TableHead>{t('exams.gpa')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {result.exams.length === 0 && (
            <TableRow><TableCell colSpan={4} className="text-center text-sm text-muted-foreground">{t('exams.noPublishedExamsYet')}</TableCell></TableRow>
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
