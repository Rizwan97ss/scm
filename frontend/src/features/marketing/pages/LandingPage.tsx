import { MarketingNav } from '../components/MarketingNav'
import { Hero } from '../components/Hero'
import { FeatureShowcase } from '../components/FeatureShowcase'
import { HowItWorks } from '../components/HowItWorks'
import { PricingSection } from '../components/PricingSection'
import { ClosingCta } from '../components/ClosingCta'
import { MarketingFooter } from '../components/MarketingFooter'

/**
 * The public marketing site -- shown at "/" only on the bare central domain
 * (see utils/tenant.ts + AppRouter). A tenant subdomain's "/" keeps its
 * existing dashboard/login behavior untouched; this page never renders
 * there. Deliberately outside ThemeContext/tenant branding -- see
 * .marketing-scope in index.css for why.
 */
export function LandingPage() {
  return (
    <div className="marketing-scope min-h-svh bg-[var(--mk-ink)]">
      <MarketingNav />
      <Hero />
      <FeatureShowcase />
      <HowItWorks />
      <PricingSection />
      <ClosingCta />
      <MarketingFooter />
    </div>
  )
}
