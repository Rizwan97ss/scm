import { describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'
import { server } from '@/testing/mswServer'
import { apiUrl, httpClient, type ApiError } from './client'

const apiV1 = `${apiUrl}/v1`

describe('httpClient response interceptor', () => {
  it('turns a 429 with a Retry-After header into a specific wait-time message', async () => {
    server.use(
      http.get(`${apiV1}/throttled-thing`, () =>
        HttpResponse.json({ success: false, message: 'Too Many Attempts.' }, { status: 429, headers: { 'Retry-After': '42' } })
      )
    )

    await expect(httpClient.get('/throttled-thing')).rejects.toMatchObject({
      message: 'Too many requests — please wait 42s and try again.',
      status: 429,
      retryAfterSeconds: 42,
    } satisfies Partial<ApiError>)
  })

  it('falls back to a generic wait message when no Retry-After header is present', async () => {
    server.use(http.get(`${apiV1}/throttled-thing`, () => HttpResponse.json({ success: false, message: 'Too Many Attempts.' }, { status: 429 })))

    await expect(httpClient.get('/throttled-thing')).rejects.toMatchObject({
      message: 'Too many requests — please wait a moment and try again.',
      status: 429,
      retryAfterSeconds: undefined,
    } satisfies Partial<ApiError>)
  })

  it('leaves a 403 message exactly as the server sent it, not overridden', async () => {
    server.use(
      http.get(`${apiV1}/forbidden-thing`, () =>
        HttpResponse.json({ success: false, message: 'You can only edit your own leave requests.' }, { status: 403 })
      )
    )

    await expect(httpClient.get('/forbidden-thing')).rejects.toMatchObject({
      message: 'You can only edit your own leave requests.',
      status: 403,
    } satisfies Partial<ApiError>)
  })

  it('leaves a normal 500 error message untouched', async () => {
    server.use(http.get(`${apiV1}/broken-thing`, () => HttpResponse.json({ success: false, message: 'Server error.' }, { status: 500 })))

    await expect(httpClient.get('/broken-thing')).rejects.toMatchObject({
      message: 'Server error.',
      status: 500,
    } satisfies Partial<ApiError>)
  })
})
