import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { Badge, Button, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { downloadFile } from '@/utils/download'
import type { BadgeVariant } from '@/components/ui/Badge'
import type { ReportCard, SubjectResultStatus } from '@/types/exam'

const STATUS_TRANSLATION_KEY: Record<SubjectResultStatus, string> = { draft: 'exams.draft', calculated: 'exams.statusCalculated', published: 'exams.published' }
const STATUS_VARIANTS: Record<SubjectResultStatus, BadgeVariant> = { draft: 'default', calculated: 'warning', published: 'success' }

export function ReportCardDisplay({ report, pdfUrl }: { report: ReportCard; pdfUrl?: string }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="font-semibold">{report.exam.name}</h3>
          {!report.exam.is_published && <Badge variant="warning">{t('exams.notYetPublished')}</Badge>}
        </div>
        {pdfUrl && (
          <Button variant="outline" onClick={() => downloadFile(pdfUrl, `${report.student.full_name}-${report.exam.name}-report-card.pdf`)}>
            <Download className="h-4 w-4" /> {t('exams.downloadPdf')}
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <StatCard label={t('exams.overallPercentage')} value={report.overall_percentage !== null ? `${report.overall_percentage}%` : '—'} />
        <StatCard label={t('exams.overallGpa')} value={report.overall_gpa ?? '—'} />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('entities.subject')}</TableHead>
            <TableHead>{t('exams.components')}</TableHead>
            <TableHead>{t('exams.total')}</TableHead>
            <TableHead>{t('exams.percentage')}</TableHead>
            <TableHead>{t('exams.grade')}</TableHead>
            <TableHead>{t('exams.passFail')}</TableHead>
            <TableHead>{t('common.status')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {report.subjects.length === 0 && (
            <TableRow><TableCell colSpan={7} className="text-center text-sm text-muted-foreground">{t('exams.noSubjectsConfiguredYet')}</TableCell></TableRow>
          )}
          {report.subjects.map((row) => (
            <TableRow key={row.group.id}>
              <TableCell className="font-medium">{row.group.subject.name}</TableCell>
              <TableCell className="text-sm text-muted-foreground">
                {row.components.length > 0
                  ? row.components.map((c) => `${c.type ?? t('exams.component')}: ${c.is_absent ? t('exams.absent') : (c.marks_obtained ?? '—')}/${c.max_marks}`).join(', ')
                  : '—'}
              </TableCell>
              <TableCell>{row.is_absent ? <Badge variant="destructive">{t('exams.absent')}</Badge> : `${row.marks_obtained_total ?? '—'} / ${row.max_marks_total}`}</TableCell>
              <TableCell>{row.percentage !== null ? `${row.percentage}%` : '—'}</TableCell>
              <TableCell>{row.grade_label ?? '—'}</TableCell>
              <TableCell>
                {row.is_pass === true && <Badge variant="success">{t('exams.pass')}</Badge>}
                {row.is_pass === false && <Badge variant="destructive">{t('exams.fail')}</Badge>}
                {row.is_pass === null && '—'}
              </TableCell>
              <TableCell>
                <Badge variant={STATUS_VARIANTS[row.group.status]}>{t(STATUS_TRANSLATION_KEY[row.group.status])}</Badge>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
