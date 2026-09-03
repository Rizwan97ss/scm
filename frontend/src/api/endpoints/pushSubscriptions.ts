import { httpClient } from '@/api/client'

export interface PushSubscriptionPayload {
  endpoint: string
  keys: { p256dh: string; auth: string }
}

export const pushSubscriptionsApi = {
  subscribe: async (payload: PushSubscriptionPayload): Promise<void> => {
    await httpClient.post('/push-subscriptions', payload)
  },
  unsubscribe: async (endpoint: string): Promise<void> => {
    await httpClient.delete('/push-subscriptions', { data: { endpoint } })
  },
}
