import type { KeyboardEvent, ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from './Table'
import { Checkbox } from './Checkbox'
import { Skeleton } from './Skeleton'
import { EmptyState } from './EmptyState'
import { QueryErrorState } from './QueryErrorState'
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
  /** Hides this column below the given breakpoint instead of letting the table scroll horizontally to fit it — for a lower-priority column (a secondary date, a subtotal next to the balance that actually matters) on a table with too many columns to comfortably fit a phone screen. Omit for columns that should always be visible. */
  hideBelow?: 'sm' | 'md' | 'lg'
}

const HIDE_BELOW_CLASS: Record<NonNullable<DataTableColumn<unknown>['hideBelow']>, string> = {
  sm: 'hidden sm:table-cell',
  md: 'hidden md:table-cell',
  lg: 'hidden lg:table-cell',
}

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[]
  data: T[] | undefined
  rowKey: (row: T) => string | number
  isLoading?: boolean
  /** When true (and not loading), renders a retry-able error state instead of an empty table body — see QueryErrorState. Pass the backing query's `isError`. */
  isError?: boolean
  /** The backing query's `refetch` — powers the error state's Retry button. Only meaningful alongside `isError`. */
  onRetry?: () => void
  meta?: PaginationMeta
  onPageChange?: (page: number) => void
  sort?: string
  onSortChange?: (sort: string) => void
  emptyTitle?: string
  emptyDescription?: string
  emptyAction?: ReactNode
  onRowClick?: (row: T) => void
  /** Renders a checkbox column and drives a bulk-action bar (see BulkActionBar) — pass both together, or neither. */
  selectedKeys?: Set<string | number>
  onSelectionChange?: (keys: Set<string | number>) => void
}

export function DataTable<T>({
  columns,
  data,
  rowKey,
  isLoading,
  isError,
  onRetry,
  meta,
  onPageChange,
  sort,
  onSortChange,
  emptyTitle,
  emptyDescription,
  emptyAction,
  onRowClick,
  selectedKeys,
  onSelectionChange,
}: DataTableProps<T>) {
  const { t } = useTranslation()
  const activeSortKey = sort?.replace('-', '')
  const activeSortDesc = sort?.startsWith('-')
  const selectable = !!selectedKeys && !!onSelectionChange
  const pageKeys = data?.map(rowKey) ?? []
  const allOnPageSelected = selectable && pageKeys.length > 0 && pageKeys.every((key) => selectedKeys!.has(key))

  function toggleAll() {
    if (!selectable) return
    const next = new Set(selectedKeys)
    if (allOnPageSelected) {
      pageKeys.forEach((key) => next.delete(key))
    } else {
      pageKeys.forEach((key) => next.add(key))
    }
    onSelectionChange!(next)
  }

  function toggleOne(key: string | number) {
    if (!selectable) return
    const next = new Set(selectedKeys)
    next.has(key) ? next.delete(key) : next.add(key)
    onSelectionChange!(next)
  }

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
            {selectable && (
              <TableHead className="w-10">
                <Checkbox checked={allOnPageSelected} onCheckedChange={toggleAll} aria-label={t('dataTable.selectAll')} />
              </TableHead>
            )}
            {columns.map((column) => (
              <TableHead
                key={column.key}
                className={cn(
                  column.align === 'right' && 'text-end',
                  column.align === 'center' && 'text-center',
                  column.sortable && 'cursor-pointer select-none hover:text-foreground',
                  column.hideBelow && HIDE_BELOW_CLASS[column.hideBelow]
                )}
                onClick={() => column.sortable && toggleSort(column.key)}
              >
                <span className="inline-flex items-center gap-1">
                  {column.header}
                  {column.sortable &&
                    (activeSortKey === column.key ? (
                      activeSortDesc ? (
                        <ArrowDown className="h-3 w-3 text-primary" />
                      ) : (
                        <ArrowUp className="h-3 w-3 text-primary" />
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
                {selectable && (
                  <TableCell>
                    <Skeleton className="h-4 w-4" />
                  </TableCell>
                )}
                {columns.map((column) => (
                  <TableCell key={column.key} className={column.hideBelow && HIDE_BELOW_CLASS[column.hideBelow]}>
                    <Skeleton className="h-4 w-full max-w-32" />
                  </TableCell>
                ))}
              </TableRow>
            ))}

          {!isLoading && isError && (
            <TableRow className="hover:bg-transparent">
              <TableCell colSpan={columns.length + (selectable ? 1 : 0)} className="p-0">
                <QueryErrorState onRetry={onRetry} />
              </TableCell>
            </TableRow>
          )}

          {!isLoading && !isError && data?.length === 0 && (
            <TableRow className="hover:bg-transparent">
              <TableCell colSpan={columns.length + (selectable ? 1 : 0)} className="p-0">
                <EmptyState title={emptyTitle ?? t('dataTable.noRecordsFound')} description={emptyDescription} action={emptyAction} />
              </TableCell>
            </TableRow>
          )}

          {!isLoading &&
            !isError &&
            data?.map((row) => {
              const key = rowKey(row)

              function handleRowKeyDown(event: KeyboardEvent<HTMLTableRowElement>) {
                if (!onRowClick) return
                if (event.key !== 'Enter' && event.key !== ' ') return
                event.preventDefault()
                onRowClick(row)
              }

              return (
                <TableRow
                  key={key}
                  onClick={() => onRowClick?.(row)}
                  onKeyDown={handleRowKeyDown}
                  role={onRowClick ? 'button' : undefined}
                  tabIndex={onRowClick ? 0 : undefined}
                  className={cn(onRowClick && 'cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset')}
                >
                  {selectable && (
                    <TableCell onClick={(e) => e.stopPropagation()}>
                      <Checkbox checked={selectedKeys!.has(key)} onCheckedChange={() => toggleOne(key)} aria-label={t('dataTable.selectRow')} />
                    </TableCell>
                  )}
                  {columns.map((column) => (
                    <TableCell
                      key={column.key}
                      className={cn(
                        column.align === 'right' && 'text-end',
                        column.align === 'center' && 'text-center',
                        column.hideBelow && HIDE_BELOW_CLASS[column.hideBelow],
                        column.className
                      )}
                    >
                      {column.render(row)}
                    </TableCell>
                  ))}
                </TableRow>
              )
            })}
        </TableBody>
      </Table>
      {meta && onPageChange && <Pagination meta={meta} onPageChange={onPageChange} />}
    </div>
  )
}
