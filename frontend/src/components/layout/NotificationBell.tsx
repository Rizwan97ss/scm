import { useEffect } from 'react'
import * as RadixDropdown from '@radix-ui/react-dropdown-menu'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, CheckCheck } from 'lucide-react'
import { toast } from 'sonner'
import { notificationsApi } from '@/api/endpoints/communication'
import { queryKeys } from '@/api/queryKeys'
import { Button } from '@/components/ui/Button'
import { useAuth } from '@/context/AuthContext'
import { getEcho } from '@/lib/echo'
import { formatDateTime } from '@/utils/formatDate'
import { cn } from '@/utils/cn'
import type { AppNotification } from '@/types/notifications'

export function NotificationBell() {
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const { data } = useQuery({
    queryKey: queryKeys.notifications({ per_page: 8 }),
    queryFn: () => notificationsApi.list({ per_page: 8 }),
    // Pusher (below) delivers new notifications the moment they're sent —
    // this poll is only the fallback for when Pusher isn't configured or a
    // connection drops silently, so it can stay slow.
    refetchInterval: 60_000,
  })

  // Live delivery: subscribe once per session (user.id + their tenant's
  // school.id, matching the channel name routes/channels.php authorizes —
  // see App\Events\NotificationCreated's docblock for why the school id is
  // required, not just the user id: tenant DB user ids restart per school
  // and every tenant shares the same Pusher app).
  useEffect(() => {
    if (!user?.school?.id) return

    const echo = getEcho()
    if (!echo) return

    const channel = echo.private(`tenant.${user.school.id}.user.${user.id}`)
    channel.listen('.notification.created', (notification: AppNotification) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications().slice(0, 1) })
      toast.info(notification.title, { description: notification.body })
    })

    return () => {
      echo.leave(`tenant.${user.school!.id}.user.${user.id}`)
    }
  }, [user?.id, user?.school?.id, queryClient])

  const markReadMutation = useMutation({
    mutationFn: (id: number) => notificationsApi.markRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.notifications().slice(0, 1) }),
  })

  const markAllReadMutation = useMutation({
    mutationFn: () => notificationsApi.markAllRead(),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.notifications().slice(0, 1) }),
  })

  const notifications = data?.data ?? []
  const unreadCount = data?.meta.unread_count ?? 0

  return (
    <RadixDropdown.Root>
      <RadixDropdown.Trigger asChild>
        <button
          type="button"
          className="relative flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label={unreadCount > 0 ? `Notifications (${unreadCount} unread)` : 'Notifications'}
        >
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute end-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-medium text-destructive-foreground">
              {unreadCount > 9 ? '9+' : unreadCount}
            </span>
          )}
        </button>
      </RadixDropdown.Trigger>
      <RadixDropdown.Portal>
        <RadixDropdown.Content align="end" sideOffset={4} className="z-50 w-80 rounded-md border border-border bg-card text-foreground shadow-lg">
          <div className="flex items-center justify-between border-b border-border px-3 py-2">
            <span className="text-sm font-semibold">Notifications</span>
            {unreadCount > 0 && (
              <Button variant="ghost" size="sm" onClick={() => markAllReadMutation.mutate()} isLoading={markAllReadMutation.isPending}>
                <CheckCheck className="h-3.5 w-3.5" /> Mark all read
              </Button>
            )}
          </div>
          <div className="max-h-80 overflow-y-auto">
            {notifications.length === 0 && <p className="px-3 py-6 text-center text-sm text-muted-foreground">No notifications yet.</p>}
            {notifications.map((notification) => (
              <button
                key={notification.id}
                type="button"
                onClick={() => !notification.is_read && markReadMutation.mutate(notification.id)}
                className={cn('flex w-full flex-col gap-0.5 border-b border-border px-3 py-2.5 text-start last:border-b-0 hover:bg-muted', !notification.is_read && 'bg-primary/5')}
              >
                <div className="flex items-center gap-2">
                  {!notification.is_read && <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />}
                  <span className="text-sm font-medium">{notification.title}</span>
                </div>
                <p className="line-clamp-2 text-xs text-muted-foreground">{notification.body}</p>
                <span className="text-[11px] text-muted-foreground">{formatDateTime(notification.created_at)}</span>
              </button>
            ))}
          </div>
        </RadixDropdown.Content>
      </RadixDropdown.Portal>
    </RadixDropdown.Root>
  )
}
