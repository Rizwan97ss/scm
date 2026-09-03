import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { PushNotificationToggle } from './PushNotificationToggle'
import { pushSubscriptionsApi } from '@/api/endpoints/pushSubscriptions'

vi.mock('@/config/env', () => ({ env: { vapidPublicKey: 'test-public-key' } }))
vi.mock('@/api/endpoints/pushSubscriptions', () => ({
  pushSubscriptionsApi: { subscribe: vi.fn().mockResolvedValue(undefined), unsubscribe: vi.fn().mockResolvedValue(undefined) },
}))

function mockPushApis({ existingSubscription = false }: { existingSubscription?: boolean } = {}) {
  const subscription = {
    endpoint: 'https://push.example.test/abc',
    toJSON: () => ({ endpoint: 'https://push.example.test/abc', keys: { p256dh: 'key', auth: 'auth' } }),
    unsubscribe: vi.fn().mockResolvedValue(true),
  }

  const registration = {
    pushManager: {
      getSubscription: vi.fn().mockResolvedValue(existingSubscription ? subscription : null),
      subscribe: vi.fn().mockResolvedValue(subscription),
    },
  }

  vi.stubGlobal('navigator', {
    ...navigator,
    serviceWorker: {
      register: vi.fn().mockResolvedValue(registration),
      ready: Promise.resolve(registration),
    },
  })
  vi.stubGlobal('PushManager', class {})
  vi.stubGlobal(
    'Notification',
    class {
      static requestPermission = vi.fn().mockResolvedValue('granted')
    }
  )

  return { subscription, registration }
}

describe('PushNotificationToggle', () => {
  beforeEach(() => vi.clearAllMocks())
  afterEach(() => vi.unstubAllGlobals())

  it('renders nothing when the browser has no Push API support', () => {
    vi.stubGlobal('PushManager', undefined)
    const { container } = render(<PushNotificationToggle />)
    expect(container).toBeEmptyDOMElement()
  })

  it('subscribes and calls the API when clicked while unsubscribed', async () => {
    mockPushApis()
    render(<PushNotificationToggle />)

    const button = await screen.findByRole('button', { name: /enable notifications/i })
    await userEvent.click(button)

    await waitFor(() => expect(pushSubscriptionsApi.subscribe).toHaveBeenCalledWith({
      endpoint: 'https://push.example.test/abc',
      keys: { p256dh: 'key', auth: 'auth' },
    }))
  })

  it('unsubscribes and calls the API when clicked while already subscribed', async () => {
    const { subscription } = mockPushApis({ existingSubscription: true })
    render(<PushNotificationToggle />)

    const button = await screen.findByRole('button', { name: /disable notifications/i })
    await userEvent.click(button)

    await waitFor(() => expect(pushSubscriptionsApi.unsubscribe).toHaveBeenCalledWith('https://push.example.test/abc'))
    expect(subscription.unsubscribe).toHaveBeenCalled()
  })
})
