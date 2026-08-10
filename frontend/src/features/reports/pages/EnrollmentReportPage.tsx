import { useQuery } from '@tanstack/react-query'
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { reportsApi } from '@/api/endpoints/reports'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, Skeleton, StatCard } from '@/components/ui'

export function EnrollmentReportPage() {
  const { data, isLoading } = useQuery({ queryKey: queryKeys.reportsEnrollment, queryFn: reportsApi.enrollment })

  return (
    <div>
      <PageHeader title="Enrollment Report" description="Admissions, withdrawals, and graduations over the last six months." />

      {isLoading && <Skeleton className="h-64 w-full" />}

      {data && (
        <>
          <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Active Students" value={data.active_total} />
          </div>

          <Card className="mb-6">
            <CardContent className="pt-6">
              <ResponsiveContainer width="100%" height={280}>
                <BarChart data={data.trend}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                  <XAxis dataKey="month" fontSize={12} />
                  <YAxis allowDecimals={false} fontSize={12} />
                  <Tooltip />
                  <Legend />
                  <Bar dataKey="admissions" name="Admissions" fill="var(--color-success)" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="withdrawals" name="Withdrawals" fill="var(--color-destructive)" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="graduations" name="Graduations" fill="var(--color-primary)" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <h4 className="mb-2 text-xs font-medium uppercase text-muted-foreground">Active Students by Grade</h4>
              {Object.keys(data.active_by_grade).length === 0 && <p className="text-sm text-muted-foreground">No active students yet.</p>}
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
