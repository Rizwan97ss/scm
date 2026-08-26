import { Link } from 'react-router-dom'
import { AlertTriangle } from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { usePermission } from '@/hooks/usePermission'
import { routePaths } from '@/routes/routePaths'
import { formatDate } from '@/utils/formatDate'

const LOCKED_STATUSES = ['canceled', 'unpaid', 'incomplete_expired']

/**
 * Reads the billing snapshot already carried on the logged-in user (see
 * UserResource.school) — no extra request. Shown to every staff member,
 * not just the School Admin, since a past-due/locked school affects
 * everyone's read/write access, not just who can fix it; the "Manage
 * billing" link itself is gated by billing.manage so non-admins don't hit
 * a 403 clicking it.
 */
export function BillingStatusBanner() {
  const { user } = useAuth()
  const { can } = usePermission()
  const school = user?.school

  if (!school?.billing_status) return null

  const isLocked = LOCKED_STATUSES.includes(school.billing_status)
  const isPastDue = school.billing_status === 'past_due'
  const trialEndingSoon =
    school.billing_status === 'trialing' &&
    school.trial_ends_at &&
    new Date(school.trial_ends_at).getTime() - Date.now() < 3 * 24 * 60 * 60 * 1000

  if (!isLocked && !isPastDue && !trialEndingSoon) return null

  const message = isLocked
    ? "This school's subscription is inactive."
    : isPastDue
      ? 'Your last payment failed — this school is in read-only mode until billing is updated.'
      : `Your trial ends ${school.trial_ends_at ? formatDate(school.trial_ends_at) : 'soon'}.`

  return (
    <div className={`flex items-center gap-2 border-b px-4 py-2 text-sm sm:px-6 ${isLocked || isPastDue ? 'border-destructive/20 bg-destructive/10 text-destructive' : 'border-warning/20 bg-warning/10 text-warning'}`}>
      <AlertTriangle className="h-4 w-4 shrink-0" />
      <span className="flex-1">{message}</span>
      {can('billing.manage') && (
        <Link to={routePaths.settingsBilling} className="shrink-0 font-medium underline">
          Manage billing
        </Link>
      )}
    </div>
  )
}
