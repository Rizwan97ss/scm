import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2 } from 'lucide-react'
import { fetchMe } from '@/api/endpoints/auth'
import { queryKeys } from '@/api/queryKeys'
import { routePaths } from '@/routes/routePaths'
import { LoadingScreen } from '@/components/feedback/LoadingScreen'

/**
 * Landed on after Stripe Checkout redirects back (success_url, see
 * SignupController). The webhook that flips billing_status from null to
 * 'trialing' fires asynchronously — near-instant in practice, but not
 * guaranteed to have landed before this page does — so this polls /auth/me
 * briefly rather than assuming it's already there.
 */
export function SignupCompletePage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: user } = useQuery({
    queryKey: queryKeys.me,
    queryFn: fetchMe,
    refetchInterval: (query) => (query.state.data?.school?.billing_status ? false : 1500),
  })

  const billingConfirmed = !!user?.school?.billing_status

  useEffect(() => {
    if (!billingConfirmed) return
    queryClient.setQueryData(queryKeys.me, user)
    const timeout = setTimeout(() => navigate(routePaths.dashboard, { replace: true }), 1200)
    return () => clearTimeout(timeout)
  }, [billingConfirmed, user, queryClient, navigate])

  if (!billingConfirmed) {
    return <LoadingScreen />
  }

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 text-center">
      <CheckCircle2 className="h-12 w-12 text-success" />
      <h1 className="text-xl font-semibold">You're all set!</h1>
      <p className="text-sm text-muted-foreground">
        {user?.school?.name}'s trial is active. Taking you to your dashboard…
      </p>
    </div>
  )
}
