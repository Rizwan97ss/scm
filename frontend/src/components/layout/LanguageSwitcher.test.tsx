import { describe, expect, it, afterEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderWithProviders } from '@/testing/testUtils'
import { LanguageSwitcher } from './LanguageSwitcher'

describe('LanguageSwitcher', () => {
  afterEach(() => {
    localStorage.removeItem('sms.language')
    document.documentElement.removeAttribute('dir')
    document.documentElement.removeAttribute('lang')
  })

  it('lists every supported language and switches on selection, flipping dir for Arabic', async () => {
    const user = userEvent.setup()
    renderWithProviders(<LanguageSwitcher />)

    await user.click(screen.getByRole('button', { name: 'Language' }))
    expect(screen.getByText('العربية')).toBeInTheDocument()
    expect(screen.getByText('Español')).toBeInTheDocument()

    await user.click(screen.getByText('العربية'))

    await waitFor(() => expect(document.documentElement.dir).toBe('rtl'))
    expect(localStorage.getItem('sms.language')).toBe('ar')
  })
})
