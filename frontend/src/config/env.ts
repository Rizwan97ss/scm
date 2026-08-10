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
  isDev: import.meta.env.DEV,
  isProd: import.meta.env.PROD,
} as const
