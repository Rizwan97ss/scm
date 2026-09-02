import { useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Search, X } from 'lucide-react'
import { searchApi } from '@/api/endpoints/search'
import { queryKeys } from '@/api/queryKeys'
import { useDebounce } from '@/hooks/useDebounce'
import { routePaths } from '@/routes/routePaths'
import { cn } from '@/utils/cn'
import type { SearchResultCategory, SearchResultItem } from '@/types/search'

const CATEGORY_LABEL_KEYS: Record<SearchResultCategory, string> = {
  students: 'common.search.categories.students',
  guardians: 'common.search.categories.guardians',
  staff: 'common.search.categories.staff',
  books: 'common.search.categories.books',
  invoices: 'common.search.categories.invoices',
}

export function GlobalSearch() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const debouncedQuery = useDebounce(query, 300)
  const containerRef = useRef<HTMLDivElement>(null)

  const { data, isFetching } = useQuery({
    queryKey: queryKeys.search(debouncedQuery),
    queryFn: () => searchApi.search(debouncedQuery),
    enabled: debouncedQuery.trim().length >= 2,
  })

  function goTo(category: SearchResultCategory, item: SearchResultItem) {
    setOpen(false)
    setQuery('')
    if (category === 'students') navigate(routePaths.studentProfile(item.id))
    else if (category === 'invoices') navigate(routePaths.invoiceDetail(item.id))
    else if (category === 'guardians') navigate(routePaths.guardians)
    else if (category === 'staff') navigate(routePaths.users)
    else if (category === 'books') navigate(routePaths.books)
  }

  const categories = data ? (Object.keys(data.results) as SearchResultCategory[]).filter((c) => (data.results[c]?.length ?? 0) > 0) : []
  const showPanel = open && debouncedQuery.trim().length >= 2

  return (
    <div ref={containerRef} className="relative hidden w-full max-w-xs sm:block">
      <div className="relative">
        <Search className="pointer-events-none absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <input
          type="search"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          placeholder={t('common.search.placeholder')}
          aria-label={t('common.search.ariaLabel')}
          className={cn(
            'h-9 w-full rounded-md border border-input bg-background ps-8 pe-8 text-sm',
            'placeholder:text-muted-foreground',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
          )}
        />
        {query && (
          <button
            type="button"
            onClick={() => setQuery('')}
            className="absolute end-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            aria-label={t('common.search.clear')}
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {showPanel && (
        <div className="absolute inset-x-0 top-full z-50 mt-1 max-h-96 overflow-y-auto rounded-md border border-border bg-card shadow-lg">
          {isFetching && <p className="px-3 py-4 text-center text-sm text-muted-foreground">{t('common.search.searching')}</p>}
          {!isFetching && categories.length === 0 && (
            <p className="px-3 py-4 text-center text-sm text-muted-foreground">{t('common.search.noResultsFor', { query: debouncedQuery })}</p>
          )}
          {!isFetching &&
            categories.map((category) => (
              <div key={category} className="border-b border-border py-1 last:border-b-0">
                <p className="px-3 py-1 text-xs font-semibold uppercase text-muted-foreground">{t(CATEGORY_LABEL_KEYS[category])}</p>
                {data?.results[category]?.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => goTo(category, item)}
                    className="flex w-full flex-col px-3 py-1.5 text-start text-sm hover:bg-muted"
                  >
                    <span className="font-medium">{item.label}</span>
                    {item.sublabel && <span className="text-xs text-muted-foreground">{item.sublabel}</span>}
                  </button>
                ))}
              </div>
            ))}
        </div>
      )}
    </div>
  )
}
