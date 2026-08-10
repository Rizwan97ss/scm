import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'

export interface Breadcrumb {
  label: string
  to?: string
}

export function Breadcrumbs({ items }: { items: Breadcrumb[] }) {
  return (
    <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-sm text-muted-foreground">
      {items.map((item, index) => (
        <span key={`${item.label}-${index}`} className="flex items-center gap-1.5">
          {index > 0 && <ChevronRight className="h-3.5 w-3.5" aria-hidden="true" />}
          {item.to ? (
            <Link to={item.to} className="hover:text-foreground hover:underline">
              {item.label}
            </Link>
          ) : (
            <span className={index === items.length - 1 ? 'font-medium text-foreground' : ''}>{item.label}</span>
          )}
        </span>
      ))}
    </nav>
  )
}
