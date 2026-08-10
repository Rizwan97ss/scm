import { forwardRef } from 'react'
import { Link, type LinkProps } from 'react-router-dom'
import { buttonClasses, type ButtonSize, type ButtonVariant } from './Button'

export interface LinkButtonProps extends LinkProps {
  variant?: ButtonVariant
  size?: ButtonSize
}

/** A react-router Link styled like Button — for navigation actions that must not be a <button>. */
export const LinkButton = forwardRef<HTMLAnchorElement, LinkButtonProps>(
  ({ className, variant = 'primary', size = 'md', ...props }, ref) => {
    return <Link ref={ref} className={buttonClasses(variant, size, className)} {...props} />
  }
)
LinkButton.displayName = 'LinkButton'
