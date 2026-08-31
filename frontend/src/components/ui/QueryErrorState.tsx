import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { Button } from './Button'
import { EmptyState } from './EmptyState'

export interface QueryErrorStateProps {
  title?: string
  description?: string
  onRetry?: () => void
}

/**
 * The shared "this failed to load" state — a failed query previously had no
 * visible representation at all (DataTable rendered just its header with an
 * empty body; detail pages stuck on their loading skeleton forever, since
 * both only ever checked `isLoading`/`!data`, never `isError`). Reuses
 * EmptyState rather than being a new visual language, just with an error
 * icon and a retry action.
 */
export function QueryErrorState({ title, description, onRetry }: QueryErrorStateProps) {
  const { t } = useTranslation()
  return (
    <EmptyState
      icon={<AlertTriangle className="h-6 w-6" />}
      title={title ?? t('queryError.title')}
      description={description ?? t('queryError.description')}
      action={
        onRetry && (
          <Button variant="outline" onClick={onRetry}>
            {t('queryError.retry')}
          </Button>
        )
      }
    />
  )
}
