import Echo from 'laravel-echo'
import Pusher, { type ChannelAuthorizerGenerator } from 'pusher-js'
import { env } from '@/config/env'
import { httpClient } from '@/api/client'

const authorizer: ChannelAuthorizerGenerator = (channel) => ({
  authorize(socketId, callback) {
    httpClient
      .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
      .then((response) => callback(null, response.data))
      .catch((error) => callback(error, null))
  },
})

let echo: Echo<'pusher'> | null = null

/**
 * Lazily creates the single shared Echo/Pusher connection, or returns null
 * if Pusher isn't configured (VITE_PUSHER_APP_KEY unset) — live
 * notifications are additive, not required; NotificationBell's own 60s
 * poll is what the app relies on either way.
 *
 * The authorizer deliberately reuses httpClient (src/api/client.ts) rather
 * than Echo's default XHR-based auth transport: this app's private
 * channels require the same Sanctum cookie-session + CSRF-token + tenant-
 * subdomain-aware base URL every other authenticated request goes
 * through, none of which Echo's default transport knows about.
 */
export function getEcho(): Echo<'pusher'> | null {
  if (!env.pusherKey || !env.pusherCluster) return null

  echo ??= new Echo({
    broadcaster: 'pusher',
    Pusher,
    key: env.pusherKey,
    cluster: env.pusherCluster,
    forceTLS: true,
    authorizer,
  })

  return echo
}

/** Torn down on logout — an active Echo connection must not outlive the session that authorized it. */
export function disconnectEcho(): void {
  echo?.disconnect()
  echo = null
}
