import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { School } from 'lucide-react'
import { routePaths } from '@/routes/routePaths'
import { env } from '@/config/env'
import { cn } from '@/utils/cn'

export function MarketingNav() {
  const [scrolled, setScrolled] = useState(false)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <header
      className={cn(
        'fixed inset-x-0 top-0 z-40 transition-colors duration-300',
        scrolled ? 'border-b border-[var(--mk-line-dark)] bg-[var(--mk-ink)]/90 backdrop-blur' : 'border-b border-transparent bg-transparent'
      )}
    >
      <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 sm:px-8">
        <Link to="/" className="flex items-center gap-2.5">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--mk-marigold)] text-[var(--mk-marigold-ink)]">
            <School className="h-4 w-4" />
          </span>
          <span className="font-[var(--mk-font-display)] text-[17px] font-medium text-[var(--mk-paper)]">{env.appName}</span>
        </Link>

        <nav className="flex items-center gap-3 sm:gap-5">
          <Link
            to={routePaths.platformLogin}
            className="hidden text-[13px] text-[var(--mk-mist)] transition-colors hover:text-[var(--mk-paper)] sm:inline"
          >
            Platform login
          </Link>
          <Link to={routePaths.login} className="text-sm text-[var(--mk-mist)] transition-colors hover:text-[var(--mk-paper)]">
            Sign in
          </Link>
          <Link
            to={routePaths.signup}
            className="rounded-full bg-[var(--mk-marigold)] px-4 py-2 text-sm font-semibold text-[var(--mk-marigold-ink)] transition-transform hover:scale-[1.03] active:scale-[0.98]"
          >
            Get started
          </Link>
        </nav>
      </div>
    </header>
  )
}
