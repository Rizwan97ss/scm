import type { ReactNode } from 'react'
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from './Table'
import { Skeleton } from './Skeleton'
import { EmptyState } from './EmptyState'
import { Pagination } from './Pagination'
import type { PaginationMeta } from '@/types/api'
import { cn } from '@/utils/cn'

export interface DataTableColumn<T> {
  key: string
  header: string
  sortable?: boolean
  align?: 'left' | 'right' | 'center'
  render: (row: T) => ReactNode
  className?: string
}

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[]
  data: T[] | undefined
  rowKey: (row: T) => string | number
  isLoading?: boolean
  meta?: PaginationMeta
  onPageChange?: (page: number) => void
  sort?: string
  onSortChange?: (sort: string) => void
  emptyTitle?: string
  emptyDescription?: string
  emptyAction?: ReactNode
  onRowClick?: (row: T) => void
}

export function DataTable<T>({
  columns,
  data,
  rowKey,
  isLoading,
  meta,
  onPageChange,
  sort,
  onSortChange,
  emptyTitle = 'No records found',
  emptyDescription,
  emptyAction,
  onRowClick,
}: DataTableProps<T>) {
  const activeSortKey = sort?.replace('-', '')
  const activeSortDesc = sort?.startsWith('-')

  function toggleSort(key: string) {
    if (!onSortChange) return
    if (activeSortKey !== key) return onSortChange(key)
    onSortChange(activeSortDesc ? key : `-${key}`)
  }

  return (
    <div className="flex flex-col">
      <Table>
        <TableHeader>
          <TableRow>
            {columns.map((column) => (
              <TableHead
                key={column.key}
                className={cn(
                  column.align === 'right' && 'text-right',
                  column.align === 'center' && 'text-center',
                  column.sortable && 'cursor-pointer select-none hover:text-foreground'
                )}
                onClick={() => column.sortable && toggleSort(column.key)}
              >
                <span className="inline-flex items-center gap-1">
                  {column.header}
                  {column.sortable &&
                    (activeSortKey === column.key ? (
                      activeSortDesc ? (
                        <ArrowDown className="h-3 w-3" />
                      ) : (
                        <ArrowUp className="h-3 w-3" />
                      )
                    ) : (
                      <ArrowUpDown className="h-3 w-3 opacity-40" />
                    ))}
                </span>
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading &&
            Array.from({ length: 5 }).map((_, i) => (
              <TableRow key={`skeleton-${i}`}>
                {columns.map((column) => (
                  <TableCell key={column.key}>
                    <Skeleton className="h-4 w-full max-w-32" />
                  </TableCell>
                ))}
              </TableRow>
            ))}

          {!isLoading && data?.length === 0 && (
            <TableRow>
              <TableCell colSpan={columns.length} className="p-0">
                <EmptyState title={emptyTitle} description={emptyDescription} action={emptyAction} />
              </TableCell>
            </TableRow>
          )}

          {!isLoading &&
            data?.map((row) => (
              <TableRow
                key={rowKey(row)}
                onClick={() => onRowClick?.(row)}
                className={cn(onRowClick && 'cursor-pointer')}
              >
                {columns.map((column) => (
                  <TableCell
                    key={column.key}
                    className={cn(column.align === 'right' && 'text-right', column.align === 'center' && 'text-center', column.className)}
                  >
                    {column.render(row)}
                  </TableCell>
                ))}
              </TableRow>
            ))}
        </TableBody>
      </Table>
      {meta && onPageChange && <Pagination meta={meta} onPageChange={onPageChange} />}
    </div>
  )
}
