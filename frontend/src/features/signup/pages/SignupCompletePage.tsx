import { useEffect, useRef, useState } from 'react'
import { useNavigate, useSearchParams, Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { XCircle } from 'lucide-react'
import { signupComplete } from '@/api/endpoints/auth'
import { queryKeys } from '@/api/queryKeys'
import { routePaths } from '@/routes/routePaths'
import { LoadingScreen } from '@/components/feedback/LoadingScreen'
import { PublicLayout } from '@/components/layout/PublicLayout'

/**
 * Landed on after Stripe Checkout redirects back, on the new tenant's own
 * subdomain (success_url, see SignupController/SignupCompleteController) —
 * NOT logged in yet at this point (see SignupResponse's docblock: a session
 * from the central signup domain can't carry over here). email + token in
 * the query string are the one-time credential provision() minted for
 * exactly this handoff; POSTing them here is what actually logs the admin
 * in, on the correct origin.
 */
export function SignupCompletePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [searchParams] = useSearchParams()
  const [status, setStatus] = useState<'pending' | 'error'>('pending')
  const attempted = useRef(false)

  const email = searchParams.get('email')
  const token = searchParams.get('token')

  useEffect(() => {
    if (attempted.current) return
    attempted.current = true

    if (!email || !token) {
      setStatus('error')
      return
    }

    signupComplete({ email, token })
      .then((user) => {
        queryClient.setQueryData(queryKeys.me, user)
        navigate(routePaths.dashboard, { replace: true })
      })
      .catch(() => {
        setStatus('error')
      })
  }, [email, token, queryClient, navigate])

  if (status === 'pending') {
    return <LoadingScreen label={t('auth.signingYouIn')} />
  }

  return (
    <PublicLayout>
      <div className="flex flex-col items-center gap-3 py-6 text-center">
        <XCircle className="h-12 w-12 text-destructive" />
        <h1 className="text-xl font-semibold">{t('auth.signInLinkInvalidHeading')}</h1>
        <p className="text-sm text-muted-foreground">{t('auth.signInLinkInvalidDescription')}</p>
        <Link to={routePaths.login} className="text-sm text-primary hover:underline">
          {t('auth.goToLoginLink')}
        </Link>
      </div>
    </PublicLayout>
  )
}
