import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { feeReportsApi } from '@/api/endpoints/fees'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, FormField, Input, Skeleton, StatCard } from '@/components/ui'
import { formatCurrency } from '@/utils/formatCurrency'

function startOfMonth() {
  const now = new Date()
  return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
}

function today() {
  return new Date().toISOString().slice(0, 10)
}

export function FeeReportsPage() {
  const { t } = useTranslation()
  const [fromDate, setFromDate] = useState(startOfMonth())
  const [toDate, setToDate] = useState(today())

  const { data: collection, isLoading: collectionLoading } = useQuery({
    queryKey: queryKeys.feeReportsCollectionSummary({ from_date: fromDate, to_date: toDate }),
    queryFn: () => feeReportsApi.collectionSummary({ from_date: fromDate, to_date: toDate }),
  })
  const { data: outstanding, isLoading: outstandingLoading } = useQuery({
    queryKey: queryKeys.feeReportsOutstandingDues,
    queryFn: () => feeReportsApi.outstandingDues(),
  })

  return (
    <div>
      <PageHeader title={t('nav.fee_reports')} description={t('fees.reportsDescription')} />

      <div className="mb-6 flex flex-wrap items-end gap-4">
        <FormField label={t('common.from')} htmlFor="from_date">
          <Input id="from_date" type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} />
        </FormField>
        <FormField label={t('common.to')} htmlFor="to_date">
          <Input id="to_date" type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} />
        </FormField>
      </div>

      {collectionLoading && <Skeleton className="h-32 w-full" />}
      {collection && (
        <div className="mb-6">
          <h3 className="mb-3 text-sm font-semibold">{t('fees.collectionsHeading', { from: collection.from_date, to: collection.to_date })}</h3>
          <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label={t('fees.totalCollected')} value={formatCurrency(collection.total_collected)} />
            <StatCard label={t('fees.paymentsCount')} value={collection.payment_count} />
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Card>
              <CardContent className="pt-6">
                <h4 className="mb-2 text-xs font-medium uppercase text-muted-foreground">{t('fees.byMethod')}</h4>
                {Object.entries(collection.by_method).length === 0 && <p className="text-sm text-muted-foreground">{t('fees.noPaymentsInRange')}</p>}
                <ul className="flex flex-col gap-1">
                  {Object.entries(collection.by_method).map(([method, amount]) => (
                    <li key={method} className="flex justify-between text-sm">
                      <span className="capitalize text-muted-foreground">{method.replace('_', ' ')}</span>
                      <span className="font-medium">{formatCurrency(amount)}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <h4 className="mb-2 text-xs font-medium uppercase text-muted-foreground">{t('fees.byCategory')}</h4>
                {Object.entries(collection.by_category).length === 0 && <p className="text-sm text-muted-foreground">{t('fees.noPaymentsInRange')}</p>}
                <ul className="flex flex-col gap-1">
                  {Object.entries(collection.by_category).map(([category, amount]) => (
                    <li key={category} className="flex justify-between text-sm">
                      <span className="text-muted-foreground">{category}</span>
                      <span className="font-medium">{formatCurrency(amount)}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          </div>
        </div>
      )}

      {outstandingLoading && <Skeleton className="h-32 w-full" />}
      {outstanding && (
        <div>
          <h3 className="mb-3 text-sm font-semibold">{t('fees.outstandingDuesHeading')}</h3>
          <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label={t('fees.totalOutstanding')} value={formatCurrency(outstanding.total_outstanding)} />
            <StatCard label={t('fees.overdueInvoices')} value={outstanding.overdue_count} />
            <StatCard label={t('fees.unpaidInvoices')} value={outstanding.invoice_count} />
          </div>
          <Card>
            <CardContent className="pt-6">
              <h4 className="mb-2 text-xs font-medium uppercase text-muted-foreground">{t('fees.byGradeLevel')}</h4>
              {Object.entries(outstanding.by_grade_level).length === 0 && <p className="text-sm text-muted-foreground">{t('fees.nothingOutstanding')}</p>}
              <ul className="flex flex-col gap-1">
                {Object.entries(outstanding.by_grade_level).map(([grade, amount]) => (
                  <li key={grade} className="flex justify-between text-sm">
                    <span className="text-muted-foreground">{grade}</span>
                    <span className="font-medium">{formatCurrency(amount)}</span>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}
