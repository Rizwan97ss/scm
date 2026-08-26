import { Link } from 'react-router-dom'
import { School } from 'lucide-react'
import { routePaths } from '@/routes/routePaths'
import { env } from '@/config/env'

export function MarketingFooter() {
  return (
    <footer className="border-t border-[var(--mk-line-dark)] py-10">
      <div className="mx-auto flex max-w-6xl flex-col items-center gap-4 px-5 text-center sm:flex-row sm:justify-between sm:text-left sm:px-8">
        <div className="flex items-center gap-2 text-[var(--mk-mist)]">
          <School className="h-4 w-4" />
          <span className="text-[13.5px]">
            {env.appName} — {new Date().getFullYear()}
          </span>
        </div>
        <nav className="flex items-center gap-6 text-[13.5px] text-[var(--mk-mist-soft)]">
          <Link to={routePaths.signup} className="hover:text-[var(--mk-paper)]">Get started</Link>
          <Link to={routePaths.login} className="hover:text-[var(--mk-paper)]">Sign in</Link>
          <Link to={routePaths.platformLogin} className="hover:text-[var(--mk-paper)]">Platform login</Link>
        </nav>
      </div>
    </footer>
  )
}
