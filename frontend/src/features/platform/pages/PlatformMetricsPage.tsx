import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Bar, BarChart, CartesianGrid, Cell, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { Building2, ClipboardList, CreditCard, GraduationCap, TrendingUp, UsersRound } from 'lucide-react'
import { platformApi } from '@/api/endpoints/platform'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Card, CardContent, CardHeader, CardTitle, DataTable, Skeleton, StatCard, type DataTableColumn } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { CHART_LTR_STYLE, useChartDirection } from '@/hooks/useChartDirection'
import type { PlatformMetricSchool } from '@/types/platform'

const STATUS_LABEL_KEYS: Record<string, string> = {
  trialing: 'settings.billingStatusTrialing',
  active: 'settings.billingStatusActive',
  past_due: 'settings.billingStatusPastDue',
  canceled: 'settings.billingStatusCanceled',
  unpaid: 'settings.billingStatusUnpaid',
  incomplete_expired: 'platform.statusIncompleteExpiredLabel',
  '': 'platform.statusPendingCheckout',
}

// Status is state, not identity — colors are reserved semantic tokens
// (good/warning/critical), the same mapping PlatformSchoolsListPage's badge
// uses, not a categorical hue cycle.
const STATUS_COLOR: Record<string, string> = {
  active: 'var(--color-success)',
  trialing: 'var(--color-info)',
  past_due: 'var(--color-warning)',
  canceled: 'var(--color-destructive)',
  unpaid: 'var(--color-destructive)',
  incomplete_expired: 'var(--color-destructive)',
  '': 'var(--color-muted-foreground)',
}

const STATUS_BADGE_VARIANT: Record<string, 'success' | 'warning' | 'destructive' | 'info' | 'default'> = {
  active: 'success',
  trialing: 'info',
  past_due: 'warning',
  canceled: 'destructive',
  unpaid: 'destructive',
  incomplete_expired: 'destructive',
}

export function PlatformMetricsPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const chartDir = useChartDirection()
  const { data, isLoading } = useQuery({ queryKey: queryKeys.platformMetrics, queryFn: platformApi.metrics })

  if (isLoading || !data) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-6">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-24 w-full" />
          ))}
        </div>
        <Skeleton className="h-72 w-full" />
      </div>
    )
  }

  const statusEntries = Object.entries(data.by_billing_status).map(([status, count]) => ({
    status,
    label: STATUS_LABEL_KEYS[status] ? t(STATUS_LABEL_KEYS[status]) : status,
    count,
  }))

  const topSchoolsByStudents = [...data.schools].sort((a, b) => b.students - a.students).slice(0, 8)

  const columns: DataTableColumn<PlatformMetricSchool>[] = [
    { key: 'name', header: t('platform.schoolColumn'), render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'plan_name', header: t('settings.planLabel'), render: (row) => row.plan_name ?? '—' },
    {
      key: 'billing_status',
      header: t('platform.billingStatusColumn'),
      render: (row) =>
        row.billing_status ? (
          <Badge variant={STATUS_BADGE_VARIANT[row.billing_status] ?? 'default'}>
            {STATUS_LABEL_KEYS[row.billing_status] ? t(STATUS_LABEL_KEYS[row.billing_status]) : row.billing_status}
          </Badge>
        ) : (
          <Badge variant="default">{t(STATUS_LABEL_KEYS[''])}</Badge>
        ),
    },
    { key: 'students', header: t('nav.students'), align: 'right', render: (row) => row.students.toLocaleString() },
    { key: 'staff', header: t('platform.staffColumn'), align: 'right', render: (row) => row.staff.toLocaleString() },
    { key: 'exams', header: t('platform.examsColumn'), align: 'right', render: (row) => row.exams.toLocaleString() },
    {
      key: 'created_at',
      header: t('platform.joinedColumn'),
      render: (row) => new Date(row.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('platform.overviewTitle')} description={t('platform.overviewDescription')} />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <StatCard label={t('platform.totalSchoolsLabel')} value={data.total_schools} icon={<Building2 className="h-5 w-5" />} tone="primary" />
        <StatCard label={t('platform.totalStudentsLabel')} value={data.total_students.toLocaleString()} icon={<GraduationCap className="h-5 w-5" />} tone="violet" />
        <StatCard label={t('platform.totalStaffLabel')} value={data.total_staff.toLocaleString()} icon={<UsersRound className="h-5 w-5" />} tone="cyan" />
        <StatCard label={t('platform.examsRecordedLabel')} value={data.total_exams.toLocaleString()} icon={<ClipboardList className="h-5 w-5" />} tone="warning" />
        <StatCard label={t('platform.activeSubscriptionsLabel')} value={data.by_billing_status.active ?? 0} icon={<CreditCard className="h-5 w-5" />} tone="success" />
        <StatCard
          label={t('platform.approximateMrrLabel')}
          value={`$${(data.approximate_mrr_cents / 100).toLocaleString()}`}
          icon={<TrendingUp className="h-5 w-5" />}
          tone="rose"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t('platform.schoolGrowthHeading')}</CardTitle>
          </CardHeader>
          <CardContent>
            {data.schools_by_month.every((m) => m.count === 0) ? (
              <p className="py-8 text-center text-sm text-muted-foreground">{t('platform.noSignupsYet')}</p>
            ) : (
              <ResponsiveContainer style={CHART_LTR_STYLE} width="100%" height={240}>
                <LineChart data={data.schools_by_month}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                  <XAxis dataKey="label" fontSize={12} tickLine={false} axisLine={false} {...chartDir.horizontalAxisProps} />
                  <YAxis allowDecimals={false} fontSize={12} tickLine={false} axisLine={false} width={28} orientation={chartDir.startOrientation} />
                  <Tooltip formatter={(value) => [value, t('platform.newSchoolsTooltip')]} labelClassName="text-foreground" />
                  <Line type="monotone" dataKey="count" stroke="var(--color-primary)" strokeWidth={2} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('platform.billingStatusBreakdownHeading')}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <div style={{ minWidth: Math.max(320, statusEntries.length * 80) }}>
                <ResponsiveContainer style={CHART_LTR_STYLE} width="100%" height={240}>
                  <BarChart data={statusEntries}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                    <XAxis dataKey="label" fontSize={12} tickLine={false} axisLine={false} {...chartDir.horizontalAxisProps} />
                    <YAxis allowDecimals={false} fontSize={12} tickLine={false} axisLine={false} width={28} orientation={chartDir.startOrientation} />
                    <Tooltip formatter={(value) => [value, t('platform.schoolsTooltip')]} />
                    <Bar dataKey="count" radius={[4, 4, 0, 0]}>
                      {statusEntries.map((entry) => (
                        <Cell key={entry.status} fill={STATUS_COLOR[entry.status] ?? 'var(--color-muted-foreground)'} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {topSchoolsByStudents.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>{t('platform.largestSchoolsHeading')}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <div style={{ minWidth: Math.max(320, topSchoolsByStudents.length * 110) }}>
                <ResponsiveContainer style={CHART_LTR_STYLE} width="100%" height={280}>
                  <BarChart data={topSchoolsByStudents}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                    <XAxis dataKey="name" fontSize={12} tickLine={false} axisLine={false} {...chartDir.horizontalAxisProps} />
                    <YAxis allowDecimals={false} fontSize={12} tickLine={false} axisLine={false} width={32} orientation={chartDir.startOrientation} />
                    <Tooltip formatter={(value) => [value, t('nav.students')]} />
                    <Bar dataKey="students" fill="var(--color-primary)" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>{t('platform.allSchoolsHeading')}</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={columns}
            data={data.schools}
            rowKey={(r) => r.id}
            onRowClick={(row) => navigate(routePaths.platformSchoolDetail(row.id))}
            emptyTitle={t('platform.noSchoolsYet')}
          />
        </CardContent>
      </Card>
    </div>
  )
}
