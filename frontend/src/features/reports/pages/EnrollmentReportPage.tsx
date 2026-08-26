import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { reportsApi } from '@/api/endpoints/reports'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, Skeleton, StatCard } from '@/components/ui'
import { CHART_LTR_STYLE, useChartDirection } from '@/hooks/useChartDirection'

export function EnrollmentReportPage() {
  const { t } = useTranslation()
  const chartDir = useChartDirection()
  const { data, isLoading } = useQuery({ queryKey: queryKeys.reportsEnrollment, queryFn: reportsApi.enrollment })

  return (
    <div>
      <PageHeader title={t('nav.enrollment_report')} description={t('reports.enrollmentReportDescription')} />

      {isLoading && <Skeleton className="h-64 w-full" />}

      {data && (
        <>
          <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label={t('reports.activeStudentsLabel')} value={data.active_total} />
          </div>

          <Card className="mb-6">
            <CardContent className="pt-6">
              <ResponsiveContainer style={CHART_LTR_STYLE} width="100%" height={280}>
                <BarChart data={data.trend}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                  <XAxis dataKey="month" fontSize={12} {...chartDir.horizontalAxisProps} />
                  <YAxis allowDecimals={false} fontSize={12} orientation={chartDir.startOrientation} />
                  <Tooltip />
                  <Legend />
                  <Bar dataKey="admissions" name={t('reports.admissionsSeries')} fill="var(--color-success)" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="withdrawals" name={t('reports.withdrawalsSeries')} fill="var(--color-destructive)" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="graduations" name={t('reports.graduationsSeries')} fill="var(--color-primary)" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <h4 className="mb-2 text-xs font-medium uppercase text-muted-foreground">{t('reports.activeByGradeHeading')}</h4>
              {Object.keys(data.active_by_grade).length === 0 && <p className="text-sm text-muted-foreground">{t('reports.noActiveStudentsYet')}</p>}
              <ul className="flex flex-col gap-1">
                {Object.entries(data.active_by_grade).map(([grade, count]) => (
                  <li key={grade} className="flex justify-between text-sm">
                    <span className="text-muted-foreground">{grade}</span>
                    <span className="font-medium">{count}</span>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        </>
      )}
    </div>
  )
}
