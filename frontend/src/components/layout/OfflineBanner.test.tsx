import { act } from 'react'
import { describe, expect, it, afterEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { OfflineBanner } from './OfflineBanner'

describe('OfflineBanner', () => {
  afterEach(() => {
    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true })
  })

  it('renders nothing while online', () => {
    render(<OfflineBanner />)
    expect(screen.queryByText(/you're offline/i)).not.toBeInTheDocument()
  })

  it('shows a message once the browser goes offline', () => {
    render(<OfflineBanner />)
    act(() => {
      Object.defineProperty(navigator, 'onLine', { value: false, configurable: true })
      window.dispatchEvent(new Event('offline'))
    })
    expect(screen.getByText(/you're offline/i)).toBeInTheDocument()
  })
})
