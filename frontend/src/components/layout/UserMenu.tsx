import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Download, HelpCircle, IdCard, LogOut, Trash2, User as UserIcon } from 'lucide-react'
import { idCardsApi } from '@/api/endpoints/certificates'
import { deleteAccount } from '@/api/endpoints/auth'
import { Avatar } from '@/components/ui/Avatar'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Dropdown } from '@/components/ui/Dropdown'
import { FormField, Input } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { routePaths } from '@/routes/routePaths'
import type { ApiError } from '@/api/client'

export function UserMenu() {
  const { user, hasRole, logout } = useAuth()
  const navigate = useNavigate()
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deletePassword, setDeletePassword] = useState('')
  const [deleteError, setDeleteError] = useState<string | null>(null)

  if (!user) return null

  const isStaff = !hasRole('Student', 'Parent')

  function closeDeleteDialog(open: boolean) {
    setDeleteOpen(open)
    if (!open) {
      setDeletePassword('')
      setDeleteError(null)
    }
  }

  async function handleDeleteAccount() {
    setDeleteError(null)
    setIsDeleting(true)
    try {
      await deleteAccount(deletePassword)
      navigate(routePaths.login, { replace: true })
    } catch (error) {
      setDeleteError((error as ApiError).message)
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <>
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
            label: 'Export My Data',
            icon: <Download className="h-4 w-4" />,
            onSelect: () => navigate(routePaths.myDataExport),
          },
          {
            label: 'Setup Guide',
            icon: <HelpCircle className="h-4 w-4" />,
            onSelect: () => navigate(routePaths.help),
          },
          {
            label: 'Delete My Account',
            icon: <Trash2 className="h-4 w-4" />,
            destructive: true,
            onSelect: () => setDeleteOpen(true),
          },
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

      <ConfirmDialog
        open={deleteOpen}
        onOpenChange={closeDeleteDialog}
        title="Delete your account?"
        description="This removes your personal data (name, contact details, medical/emergency info) immediately and cannot be undone. Academic and financial records tied to your account are kept, as required."
        confirmLabel="Delete my account"
        isLoading={isDeleting}
        onConfirm={handleDeleteAccount}
      >
        <FormField label="Confirm your password" htmlFor="delete-account-password" error={deleteError ?? undefined}>
          <Input
            id="delete-account-password"
            type="password"
            autoComplete="current-password"
            value={deletePassword}
            onChange={(e) => setDeletePassword(e.target.value)}
            invalid={!!deleteError}
          />
        </FormField>
      </ConfirmDialog>
    </>
  )
}
