import { useNavigate } from 'react-router-dom'
import { IdCard, LogOut, User as UserIcon } from 'lucide-react'
import { idCardsApi } from '@/api/endpoints/certificates'
import { Avatar } from '@/components/ui/Avatar'
import { Dropdown } from '@/components/ui/Dropdown'
import { useAuth } from '@/context/AuthContext'
import { routePaths } from '@/routes/routePaths'

export function UserMenu() {
  const { user, hasRole, logout } = useAuth()
  const navigate = useNavigate()

  if (!user) return null

  const isStaff = !hasRole('Student', 'Parent')

  return (
    <Dropdown
      trigger={
        <button
          type="button"
          className="flex items-center gap-2 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label="Account menu"
        >
          <Avatar name={user.full_name} src={user.avatar_url} size={36} />
        </button>
      }
      items={[
        {
          label: user.full_name,
          icon: <UserIcon className="h-4 w-4" />,
          disabled: true,
        },
        ...(isStaff
          ? [
              {
                label: 'Download My ID Card',
                icon: <IdCard className="h-4 w-4" />,
                onSelect: () => window.open(idCardsApi.staffPdfUrl(user.id), '_blank'),
              },
            ]
          : []),
        {
          label: 'Log out',
          icon: <LogOut className="h-4 w-4" />,
          destructive: true,
          onSelect: async () => {
            await logout()
            navigate(routePaths.login)
          },
        },
      ]}
    />
  )
}
