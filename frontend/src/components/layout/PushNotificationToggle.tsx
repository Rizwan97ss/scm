import { Bell, BellOff } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { usePushSubscription } from '@/hooks/usePushSubscription'

/** Present app-wide in the Topbar, same as NotificationBell/ThemeSwitcher — not a page. */
export function PushNotificationToggle() {
  const { t } = useTranslation()
  const { isSupported, isSubscribed, isLoading, subscribe, unsubscribe } = usePushSubscription()

  if (!isSupported) return null

  return (
    <button
      type="button"
      disabled={isLoading}
      onClick={() => (isSubscribed ? unsubscribe() : subscribe())}
      className="flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
      aria-label={isSubscribed ? t('common.topbar.disableNotifications') : t('common.topbar.enableNotifications')}
      title={isSubscribed ? t('common.topbar.disableNotifications') : t('common.topbar.enableNotifications')}
    >
      {isSubscribed ? <Bell className="h-4 w-4" /> : <BellOff className="h-4 w-4" />}
    </button>
  )
}
