import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'

/** Typed read/update helpers over the URL's search params, so list filters survive a refresh/back-nav. */
export function useQueryParams() {
  const [searchParams, setSearchParams] = useSearchParams()

  const get = useCallback((key: string): string | undefined => searchParams.get(key) ?? undefined, [searchParams])

  const set = useCallback(
    (updates: Record<string, string | number | undefined | null>) => {
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev)
          for (const [key, value] of Object.entries(updates)) {
            if (value === undefined || value === null || value === '') {
              next.delete(key)
            } else {
              next.set(key, String(value))
            }
          }
          return next
        },
        { replace: true }
      )
    },
    [setSearchParams]
  )

  const params = useMemo(() => Object.fromEntries(searchParams.entries()), [searchParams])

  return { get, set, params }
}
