import { useQuery } from '@tanstack/react-query'
import { Download } from 'lucide-react'
import { certificatesApi } from '@/api/endpoints/certificates'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import type { Certificate } from '@/types/certificates'

export function CertificatesPage() {
  const { can } = usePermission()
  const canViewAll = can('certificates.create') || can('certificates.edit')
  const { setPage, queryParams } = usePagination('-issued_date')
  const listQuery = useQuery({ queryKey: queryKeys.certificates(queryParams), queryFn: () => certificatesApi.list(queryParams) })

  const columns: DataTableColumn<Certificate>[] = [
    ...(canViewAll ? [{ key: 'student', header: 'Student', render: (row: Certificate) => row.student?.full_name ?? '—' } satisfies DataTableColumn<Certificate>] : []),
    { key: 'template', header: 'Type', render: (row) => row.certificate_template?.type ?? '—' },
    { key: 'certificate_number', header: 'Certificate #', render: (row) => row.certificate_number },
    { key: 'issued_date', header: 'Issued', render: (row) => formatDate(row.issued_date) },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <a
          href={certificatesApi.pdfUrl(row.id)}
          target="_blank"
          rel="noopener"
          className="flex items-center justify-end gap-1 text-sm text-primary hover:underline"
          aria-label={`Download certificate ${row.certificate_number}`}
        >
          <Download className="h-3.5 w-3.5" /> PDF
        </a>
      ),
    },
  ]

  return (
    <div>
      <PageHeader title="Certificates" description={canViewAll ? 'Every certificate issued to a student.' : 'Your issued certificates.'} />

      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(row) => row.id}
        isLoading={listQuery.isLoading}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        emptyTitle="No certificates yet"
      />
    </div>
  )
}
