/**
 * Typed, validated access to build-time env vars. Import this instead of
 * `import.meta.env` directly so a missing var fails fast with a clear message
 * rather than silently becoming `undefined` deep in a request.
 */
function required(name: string, value: string | undefined): string {
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`)
  }
  return value
}

export const env = {
  apiUrl: required('VITE_API_URL', import.meta.env.VITE_API_URL),
  appName: import.meta.env.VITE_APP_NAME ?? 'School Management System',
  // Optional — live notifications (src/lib/echo.ts) simply don't connect
  // if either is unset, falling back to NotificationBell's own poll.
  pusherKey: import.meta.env.VITE_PUSHER_APP_KEY as string | undefined,
  pusherCluster: import.meta.env.VITE_PUSHER_APP_CLUSTER as string | undefined,
  isDev: import.meta.env.DEV,
  isProd: import.meta.env.PROD,
} as const
