import { useQuery } from '@tanstack/react-query'
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { reportsApi } from '@/api/endpoints/reports'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, DataTable, Skeleton, type DataTableColumn } from '@/components/ui'
import type { ExamPerformance } from '@/types/reports'

export function AcademicReportPage() {
  const { data, isLoading } = useQuery({ queryKey: queryKeys.reportsAcademicPerformance, queryFn: reportsApi.academicPerformance })

  const exams = data?.exams ?? []

  const columns: DataTableColumn<ExamPerformance>[] = [
    { key: 'exam_name', header: 'Exam', render: (row) => <span className="font-medium">{row.exam_name}</span> },
    { key: 'entries_count', header: 'Marks Entered', align: 'right', render: (row) => row.entries_count },
    { key: 'average_percentage', header: 'Average', align: 'right', render: (row) => (row.average_percentage != null ? `${row.average_percentage}%` : '—') },
    { key: 'pass_rate', header: 'Pass Rate', align: 'right', render: (row) => (row.pass_rate != null ? `${row.pass_rate}%` : '—') },
  ]

  return (
    <div>
      <PageHeader title="Academic Performance" description="Average score and pass rate across the most recent published exams." />

      {isLoading && <Skeleton className="h-64 w-full" />}

      {!isLoading && exams.length === 0 && <p className="text-sm text-muted-foreground">No published exams with marks yet.</p>}

      {exams.length > 0 && (
        <>
          <Card className="mb-6">
            <CardContent className="pt-6">
              <ResponsiveContainer width="100%" height={280}>
                <BarChart data={exams}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                  <XAxis dataKey="exam_name" fontSize={12} />
                  <YAxis domain={[0, 100]} fontSize={12} />
                  <Tooltip formatter={(value) => `${value}%`} />
                  <Legend />
                  <Bar dataKey="average_percentage" name="Average %" fill="var(--color-primary)" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="pass_rate" name="Pass Rate %" fill="var(--color-success)" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          <DataTable columns={columns} data={exams} rowKey={(row) => row.exam_id} isLoading={false} />
        </>
      )}
    </div>
  )
}
