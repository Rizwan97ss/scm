import { useQueryParams } from './useQueryParams'
import type { ListQueryParams } from '@/types/api'

const DEFAULT_PER_PAGE = 15

/**
 * Page/sort/search state for a DataTable-backed list, persisted in the URL.
 * `searchFilterKey` names the backend's spatie/query-builder filter field the
 * free-text search box maps to (e.g. "first_name") — differs per resource, so
 * it's the caller's job to say which one, not this hook's.
 */
export function usePagination(defaultSort?: string, searchFilterKey?: string) {
  const { get, set } = useQueryParams()

  const page = Number(get('page') ?? 1)
  const sort = get('sort') ?? defaultSort
  const search = get('q') ?? ''

  const queryParams: ListQueryParams = {
    page,
    per_page: DEFAULT_PER_PAGE,
    ...(sort ? { sort } : {}),
    ...(search && searchFilterKey ? { [`filter[${searchFilterKey}]`]: search } : {}),
  }

  return {
    page,
    sort,
    search,
    setPage: (nextPage: number) => set({ page: nextPage }),
    setSort: (nextSort: string) => set({ sort: nextSort, page: 1 }),
    setSearch: (nextSearch: string) => set({ q: nextSearch, page: 1 }),
    queryParams,
  }
}
