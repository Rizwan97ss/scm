import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { reportsApi } from '@/api/endpoints/reports'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, FormField, Input, Skeleton, StatCard } from '@/components/ui'

function monthsAgo(n: number) {
  const now = new Date()
  return new Date(now.getFullYear(), now.getMonth() - n, 1).toISOString().slice(0, 10)
}

function today() {
  return new Date().toISOString().slice(0, 10)
}

export function AttendanceReportPage() {
  const { t } = useTranslation()
  const [fromDate, setFromDate] = useState(monthsAgo(5))
  const [toDate, setToDate] = useState(today())

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.reportsAttendance({ from_date: fromDate, to_date: toDate }),
    queryFn: () => reportsApi.attendance({ from_date: fromDate, to_date: toDate }),
  })

  return (
    <div>
      <PageHeader title={t('nav.attendance_report')} description={t('reports.attendanceReportDescription')} />

      <div className="mb-6 flex flex-wrap items-end gap-4">
        <FormField label={t('common.from')} htmlFor="from_date">
          <Input id="from_date" type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} />
        </FormField>
        <FormField label={t('common.to')} htmlFor="to_date">
          <Input id="to_date" type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} />
        </FormField>
      </div>

      {isLoading && <Skeleton className="h-64 w-full" />}

      {data?.student && (
        <div className="mb-8">
          <h3 className="mb-3 text-sm font-semibold">{t('reports.studentAttendanceHeading')}</h3>
          <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label={t('reports.overallLabel')} value={data.student.overall_percentage != null ? `${data.student.overall_percentage}%` : '—'} />
          </div>
          <TrendChart trend={data.student.trend} />
          {data.student.by_section && Object.keys(data.student.by_section).length > 0 && (
            <Card className="mt-4">
              <CardContent className="pt-6">
                <h4 className="mb-2 text-xs font-medium uppercase text-muted-foreground">{t('reports.bySectionHeading')}</h4>
                <ul className="flex flex-col gap-1">
                  {Object.entries(data.student.by_section).map(([section, percentage]) => (
                    <li key={section} className="flex justify-between text-sm">
                      <span className="text-muted-foreground">{section}</span>
                      <span className="font-medium">{percentage != null ? `${percentage}%` : '—'}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
        </div>
      )}

      {data?.staff && (
        <div>
          <h3 className="mb-3 text-sm font-semibold">{t('reports.staffAttendanceHeading')}</h3>
          <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label={t('reports.overallLabel')} value={data.staff.overall_percentage != null ? `${data.staff.overall_percentage}%` : '—'} />
          </div>
          <TrendChart trend={data.staff.trend} />
        </div>
      )}

      {!isLoading && !data?.student && !data?.staff && <p className="text-sm text-muted-foreground">{t('reports.noAttendanceDataVisible')}</p>}
    </div>
  )
}

function TrendChart({ trend }: { trend: { month: string; percentage: number | null }[] }) {
  const { t } = useTranslation()
  if (trend.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('reports.noRecordsInRange')}</p>
  }

  return (
    <Card>
      <CardContent className="pt-6">
        <ResponsiveContainer width="100%" height={240}>
          <LineChart data={trend}>
            <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
            <XAxis dataKey="month" fontSize={12} />
            <YAxis domain={[0, 100]} fontSize={12} />
            <Tooltip formatter={(value) => [`${value}%`, t('reports.attendanceTooltipLabel')]} />
            <Line type="monotone" dataKey="percentage" stroke="var(--color-primary)" strokeWidth={2} connectNulls />
          </LineChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}
