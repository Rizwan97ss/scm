import { useEffect } from 'react'

/**
 * Warns on browser-level navigation away (tab close, refresh, typed URL) while
 * `isDirty` is true. Can't intercept in-app route changes — the app uses
 * `<BrowserRouter>`, not a data router, so React Router's navigation blockers
 * aren't wireable here without migrating the router.
 */
export function useUnsavedChangesWarning(isDirty: boolean) {
  useEffect(() => {
    if (!isDirty) return

    function handleBeforeUnload(event: BeforeUnloadEvent) {
      event.preventDefault()
    }

    window.addEventListener('beforeunload', handleBeforeUnload)
    return () => window.removeEventListener('beforeunload', handleBeforeUnload)
  }, [isDirty])
}
