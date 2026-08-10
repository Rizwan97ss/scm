import { useQuery } from '@tanstack/react-query'
import { Building2, CreditCard, TrendingUp } from 'lucide-react'
import { platformApi } from '@/api/endpoints/platform'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Skeleton, StatCard } from '@/components/ui'

const STATUS_LABELS: Record<string, string> = {
  trialing: 'Trialing',
  active: 'Active',
  past_due: 'Past due',
  canceled: 'Canceled',
  unpaid: 'Unpaid',
  incomplete_expired: 'Incomplete (expired)',
}

export function PlatformMetricsPage() {
  const { data, isLoading } = useQuery({ queryKey: queryKeys.platformMetrics, queryFn: platformApi.metrics })

  if (isLoading || !data) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-32 w-full" />
      </div>
    )
  }

  return (
    <div>
      <PageHeader
        title="Metrics"
        description="A quick snapshot, not a full BI dashboard — approximate MRR ignores proration, discounts, and mid-cycle plan changes."
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label="Total schools" value={data.total_schools} icon={<Building2 className="h-5 w-5" />} />
        <StatCard label="Active subscriptions" value={data.by_billing_status.active ?? 0} icon={<CreditCard className="h-5 w-5" />} />
        <StatCard
          label="Approximate MRR"
          value={`$${(data.approximate_mrr_cents / 100).toLocaleString()}`}
          icon={<TrendingUp className="h-5 w-5" />}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:grid-cols-6">
        {Object.entries(data.by_billing_status).map(([status, count]) => (
          <StatCard key={status} label={STATUS_LABELS[status] ?? status} value={count} />
        ))}
      </div>
    </div>
  )
}
