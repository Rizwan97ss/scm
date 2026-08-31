import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { X } from 'lucide-react'
import { Button } from './Button'

interface BulkActionBarProps {
  count: number
  onClear: () => void
  children: ReactNode
}

/** Appears above a DataTable once selectedKeys is non-empty — pairs with DataTable's selectedKeys/onSelectionChange. */
export function BulkActionBar({ count, onClear, children }: BulkActionBarProps) {
  const { t } = useTranslation()
  if (count === 0) return null

  return (
    <div className="mb-3 flex flex-wrap items-center gap-3 rounded-md border border-border bg-muted/40 px-4 py-2.5">
      <span className="text-sm font-medium">{t('bulkActions.selected', { count })}</span>
      <div className="flex flex-wrap items-center gap-2">{children}</div>
      <Button variant="ghost" size="sm" className="ms-auto" onClick={onClear}>
        <X className="h-3.5 w-3.5" /> {t('bulkActions.clear')}
      </Button>
    </div>
  )
}
