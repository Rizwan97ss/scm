import { useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Search, X } from 'lucide-react'
import { searchApi } from '@/api/endpoints/search'
import { queryKeys } from '@/api/queryKeys'
import { useDebounce } from '@/hooks/useDebounce'
import { routePaths } from '@/routes/routePaths'
import { cn } from '@/utils/cn'
import type { SearchResultCategory, SearchResultItem } from '@/types/search'

const CATEGORY_LABELS: Record<SearchResultCategory, string> = {
  students: 'Students',
  guardians: 'Guardians',
  staff: 'Staff',
  books: 'Books',
  invoices: 'Invoices',
}

export function GlobalSearch() {
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
        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <input
          type="search"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          placeholder="Search students, staff, invoices..."
          aria-label="Global search"
          className={cn(
            'h-9 w-full rounded-md border border-input bg-background pl-8 pr-8 text-sm',
            'placeholder:text-muted-foreground',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
          )}
        />
        {query && (
          <button
            type="button"
            onClick={() => setQuery('')}
            className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            aria-label="Clear search"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {showPanel && (
        <div className="absolute left-0 right-0 top-full z-50 mt-1 max-h-96 overflow-y-auto rounded-md border border-border bg-card shadow-lg">
          {isFetching && <p className="px-3 py-4 text-center text-sm text-muted-foreground">Searching…</p>}
          {!isFetching && categories.length === 0 && <p className="px-3 py-4 text-center text-sm text-muted-foreground">No results for “{debouncedQuery}”.</p>}
          {!isFetching &&
            categories.map((category) => (
              <div key={category} className="border-b border-border py-1 last:border-b-0">
                <p className="px-3 py-1 text-xs font-semibold uppercase text-muted-foreground">{CATEGORY_LABELS[category]}</p>
                {data?.results[category]?.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => goTo(category, item)}
                    className="flex w-full flex-col px-3 py-1.5 text-left text-sm hover:bg-muted"
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
