import { useTranslation } from 'react-i18next'
import { WifiOff } from 'lucide-react'
import { useOnlineStatus } from '@/hooks/useOnlineStatus'

/**
 * Queries retry once automatically (see app/queryClient.ts's shouldRetryQuery)
 * so a brief drop usually self-heals, but mutations don't retry at all —
 * retrying a payment/save blindly on reconnect risks double-submitting it.
 * This banner is purely informational: it tells the user why an action they
 * just took may have failed, without attempting to fix anything for them.
 */
export function OfflineBanner() {
  const { t } = useTranslation()
  const isOnline = useOnlineStatus()
  if (isOnline) return null

  return (
    <div className="flex items-center gap-2 border-b border-warning/20 bg-warning/10 px-4 py-2 text-sm text-warning sm:px-6">
      <WifiOff className="h-4 w-4 shrink-0" />
      <span className="flex-1">{t('offlineBanner.message')}</span>
    </div>
  )
}
