import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Button } from './Button'

describe('Button', () => {
  it('renders its children', () => {
    render(<Button>Save changes</Button>)
    expect(screen.getByRole('button', { name: 'Save changes' })).toBeInTheDocument()
  })

  it('calls onClick when clicked', async () => {
    const onClick = vi.fn()
    render(<Button onClick={onClick}>Click me</Button>)
    await userEvent.click(screen.getByRole('button', { name: 'Click me' }))
    expect(onClick).toHaveBeenCalledOnce()
  })

  it('is disabled and unclickable while isLoading', async () => {
    const onClick = vi.fn()
    render(
      <Button isLoading onClick={onClick}>
        Save
      </Button>
    )
    const button = screen.getByRole('button', { name: 'Save' })
    expect(button).toBeDisabled()
    await userEvent.click(button)
    expect(onClick).not.toHaveBeenCalled()
  })

  it('respects an explicit disabled prop', () => {
    render(<Button disabled>Save</Button>)
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled()
  })
})
