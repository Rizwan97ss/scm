import { useTranslation } from 'react-i18next'
import { Search, X } from 'lucide-react'
import { Input } from './Input'
import { cn } from '@/utils/cn'

export interface SearchInputProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  className?: string
}

/**
 * The search-box markup every list page with a search filter used to
 * hand-roll identically (icon + Input, no way to clear except manually
 * deleting the text) — now with a clear (×) button that appears once
 * there's something to clear, matching the standard pattern of every
 * search box outside this app.
 */
export function SearchInput({ value, onChange, placeholder, className }: SearchInputProps) {
  const { t } = useTranslation()
  return (
    <div className={cn('relative', className)}>
      <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
      <Input placeholder={placeholder} className={cn('ps-9', value && 'pe-9')} value={value} onChange={(e) => onChange(e.target.value)} />
      {value && (
        <button
          type="button"
          onClick={() => onChange('')}
          aria-label={t('search.clear')}
          className="absolute end-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
        >
          <X className="h-3.5 w-3.5" />
        </button>
      )}
    </div>
  )
}
