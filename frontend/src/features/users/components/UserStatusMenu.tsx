import { useMutation, useQueryClient } from '@tanstack/react-query'
import { MoreVertical, KeyRound, ShieldOff, UserCheck, UserX, Ban } from 'lucide-react'
import { toast } from 'sonner'
import { usersApi } from '@/api/endpoints/users'
import { queryKeys } from '@/api/queryKeys'
import { Button, Dropdown } from '@/components/ui'
import { usePermission } from '@/hooks/usePermission'
import type { User } from '@/types/auth'
import type { UserStatus } from '@/types/enums'
import type { ApiError } from '@/api/client'

export function UserStatusMenu({ user }: { user: User }) {
  const queryClient = useQueryClient()
  const { can } = usePermission()

  const statusMutation = useMutation({
    mutationFn: (status: UserStatus) => usersApi.updateStatus(user.id, status),
    onSuccess: () => {
      toast.success('Status updated.')
      queryClient.invalidateQueries({ queryKey: queryKeys.users() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const resetPasswordMutation = useMutation({
    mutationFn: () => usersApi.resetPassword(user.id),
    onSuccess: () => toast.success('Password reset link sent.'),
    onError: (error) => toast.error((error as ApiError).message),
  })

  const resetMfaMutation = useMutation({
    mutationFn: () => usersApi.resetMfa(user.id),
    onSuccess: () => {
      toast.success('Two-factor authentication reset — they can set it up again on next login.')
      queryClient.invalidateQueries({ queryKey: queryKeys.users() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Dropdown
      trigger={
        <Button variant="outline" size="icon" aria-label="More actions">
          <MoreVertical className="h-4 w-4" />
        </Button>
      }
      items={[
        {
          label: 'Send password reset',
          icon: <KeyRound className="h-4 w-4" />,
          onSelect: () => resetPasswordMutation.mutate(),
        },
        // Lost-device recovery — see UserController::resetMfa()'s docblock
        // for why this is gated on users.manage-mfa specifically, not the
        // broader users.edit self-service-friendly check every other item
        // here effectively relies on via the parent page's own PermissionRoute.
        ...(can('users.manage-mfa') && user.mfa_enabled
          ? [
              {
                label: 'Reset two-factor authentication',
                icon: <ShieldOff className="h-4 w-4" />,
                destructive: true,
                onSelect: () => resetMfaMutation.mutate(),
              },
            ]
          : []),
        {
          label: 'Set active',
          icon: <UserCheck className="h-4 w-4" />,
          disabled: user.status === 'active',
          onSelect: () => statusMutation.mutate('active'),
        },
        {
          label: 'Set inactive',
          icon: <UserX className="h-4 w-4" />,
          disabled: user.status === 'inactive',
          onSelect: () => statusMutation.mutate('inactive'),
        },
        {
          label: 'Suspend',
          icon: <Ban className="h-4 w-4" />,
          destructive: true,
          disabled: user.status === 'suspended',
          onSelect: () => statusMutation.mutate('suspended'),
        },
      ]}
    />
  )
}
