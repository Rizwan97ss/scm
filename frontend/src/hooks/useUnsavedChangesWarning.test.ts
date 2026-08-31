import { describe, expect, it, vi, afterEach } from 'vitest'
import { renderHook } from '@testing-library/react'
import { useUnsavedChangesWarning } from './useUnsavedChangesWarning'

describe('useUnsavedChangesWarning', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('does not call preventDefault on beforeunload when not dirty', () => {
    renderHook(() => useUnsavedChangesWarning(false))
    const event = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(false)
  })

  it('calls preventDefault on beforeunload while dirty', () => {
    renderHook(() => useUnsavedChangesWarning(true))
    const event = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
  })

  it('stops warning once isDirty flips back to false', () => {
    const { rerender } = renderHook(({ isDirty }) => useUnsavedChangesWarning(isDirty), { initialProps: { isDirty: true } })
    rerender({ isDirty: false })
    const event = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(false)
  })
})
