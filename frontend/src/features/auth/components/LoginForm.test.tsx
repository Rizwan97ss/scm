import { describe, expect, it } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { LoginForm } from './LoginForm'
import { renderWithProviders } from '@/testing/testUtils'
import { server } from '@/testing/mswServer'
import { env } from '@/config/env'

const apiV1 = `${env.apiUrl}/v1`

describe('LoginForm', () => {
  it('shows validation errors when submitted empty', async () => {
    renderWithProviders(<LoginForm />)

    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('Email or username is required')).toBeInTheDocument()
    expect(screen.getByText('Password is required')).toBeInTheDocument()
  })

  it('shows a server error message on invalid credentials', async () => {
    server.use(
      http.post(`${apiV1}/auth/login`, () =>
        HttpResponse.json({ success: false, message: 'These credentials do not match our records.' }, { status: 422 })
      )
    )

    renderWithProviders(<LoginForm />)

    await userEvent.type(screen.getByLabelText(/Email or username/), 'wrong@example.com')
    await userEvent.type(screen.getByLabelText(/^Password/), 'wrong-password')
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument()
  })

  it('submits valid credentials to the login endpoint', async () => {
    let receivedBody: unknown = null
    server.use(
      http.post(`${apiV1}/auth/login`, async ({ request }) => {
        receivedBody = await request.json()
        return HttpResponse.json({
          success: true,
          message: 'Logged in successfully.',
          data: { id: 1, uuid: 'u1', first_name: 'Alice', last_name: 'Admin', full_name: 'Alice Admin', email: 'alice@example.com', username: null, phone: null, gender: null, date_of_birth: null, status: 'active', must_change_password: false, last_login_at: null, avatar_url: null, roles: ['School Admin'], created_at: '2026-01-01T00:00:00Z' },
        })
      })
    )

    renderWithProviders(<LoginForm />)

    await userEvent.type(screen.getByLabelText(/Email or username/), 'alice@example.com')
    await userEvent.type(screen.getByLabelText(/^Password/), 'correct-password')
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }))

    await waitFor(() => {
      expect(receivedBody).toMatchObject({ email: 'alice@example.com', password: 'correct-password' })
    })
  })
})
