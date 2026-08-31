import '@testing-library/jest-dom/vitest'
import { afterAll, afterEach, beforeAll } from 'vitest'
import { server } from './mswServer'

// Initializes the global i18next singleton react-i18next's useTranslation()
// falls back to when no <I18nextProvider> is in the tree — without this,
// every component using t() would throw/render untranslated in tests.
import i18n from '@/lib/i18n'

// jsdom doesn't implement ResizeObserver; several Radix primitives (Checkbox,
// Select) use it internally to measure themselves.
class ResizeObserverMock {
  observe() {}
  unobserve() {}
  disconnect() {}
}
globalThis.ResizeObserver ??= ResizeObserverMock as unknown as typeof ResizeObserver

beforeAll(() => server.listen({ onUnhandledRequest: 'warn' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

// lib/i18n.ts loads translations over HTTP (i18next-http-backend) and
// suspends components until that resolves — real strings appear as soon as
// MSW's handler above answers it, but resolving it once here up front (vs.
// making every test await a render) keeps every existing getByLabelText-style
// synchronous assertion working exactly as it did before HttpBackend existed.
beforeAll(() => i18n.changeLanguage('en'))
