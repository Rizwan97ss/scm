import { useCallback, useEffect, useState } from 'react'
import { toast } from 'sonner'
import { pushSubscriptionsApi } from '@/api/endpoints/pushSubscriptions'
import { env } from '@/config/env'
import type { ApiError } from '@/api/client'

/**
 * VAPID public keys are base64url — the Push API wants raw bytes as
 * BufferSource. Uint8Array.from(...) types as Uint8Array<ArrayBufferLike>,
 * which TS's lib.dom no longer accepts directly for applicationServerKey —
 * .buffer.slice(0) copies into a real, non-shared ArrayBuffer to satisfy it.
 */
function urlBase64ToUint8Array(base64Url: string): BufferSource {
  const padding = '='.repeat((4 - (base64Url.length % 4)) % 4)
  const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(base64)
  const bytes = Uint8Array.from([...raw].map((char) => char.charCodeAt(0)))
  return bytes.buffer.slice(0)
}

function toSubscriptionPayload(subscription: PushSubscription) {
  const json = subscription.toJSON()
  return {
    endpoint: json.endpoint!,
    keys: { p256dh: json.keys!.p256dh, auth: json.keys!.auth },
  }
}

/**
 * Browser Push API + VAPID, not Firebase — see WebPushGateway's backend
 * docblock for why. Registers /sw.js (idempotent) on mount, exposes whether
 * this browser is already subscribed, and subscribe()/unsubscribe() to
 * flip it — both explicit user actions, never called automatically, since
 * requesting Notification permission on page load is exactly the pattern
 * browsers penalize and users resent.
 */
export function usePushSubscription() {
  const isSupported = 'serviceWorker' in navigator && 'PushManager' in window && !!env.vapidPublicKey
  const [isSubscribed, setIsSubscribed] = useState(false)
  const [isLoading, setIsLoading] = useState(false)

  useEffect(() => {
    if (!isSupported) return
    let cancelled = false

    navigator.serviceWorker
      .register('/sw.js')
      .then((registration) => registration.pushManager.getSubscription())
      .then((subscription) => {
        if (!cancelled) setIsSubscribed(!!subscription)
      })
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [isSupported])

  const subscribe = useCallback(async () => {
    if (!isSupported) return
    setIsLoading(true)
    try {
      const permission = await Notification.requestPermission()
      if (permission !== 'granted') return

      const registration = await navigator.serviceWorker.ready
      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(env.vapidPublicKey!),
      })

      await pushSubscriptionsApi.subscribe(toSubscriptionPayload(subscription))
      setIsSubscribed(true)
    } catch (error) {
      toast.error((error as ApiError).message ?? 'Could not enable notifications.')
    } finally {
      setIsLoading(false)
    }
  }, [isSupported])

  const unsubscribe = useCallback(async () => {
    if (!isSupported) return
    setIsLoading(true)
    try {
      const registration = await navigator.serviceWorker.ready
      const subscription = await registration.pushManager.getSubscription()
      if (subscription) {
        await pushSubscriptionsApi.unsubscribe(subscription.endpoint)
        await subscription.unsubscribe()
      }
      setIsSubscribed(false)
    } catch (error) {
      toast.error((error as ApiError).message ?? 'Could not disable notifications.')
    } finally {
      setIsLoading(false)
    }
  }, [isSupported])

  return { isSupported, isSubscribed, isLoading, subscribe, unsubscribe }
}
