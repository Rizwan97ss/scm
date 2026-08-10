import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Modal } from './Modal'

describe('Modal', () => {
  it('renders title and content when open', () => {
    render(
      <Modal open onOpenChange={() => {}} title="Edit Student">
        <p>Form contents</p>
      </Modal>
    )
    expect(screen.getByRole('heading', { name: 'Edit Student' })).toBeInTheDocument()
    expect(screen.getByText('Form contents')).toBeInTheDocument()
  })

  it('does not render content when closed', () => {
    render(
      <Modal open={false} onOpenChange={() => {}} title="Edit Student">
        <p>Form contents</p>
      </Modal>
    )
    expect(screen.queryByText('Form contents')).not.toBeInTheDocument()
  })

  it('calls onOpenChange(false) when the close button is clicked', async () => {
    const onOpenChange = vi.fn()
    render(
      <Modal open onOpenChange={onOpenChange} title="Edit Student">
        <p>Form contents</p>
      </Modal>
    )
    await userEvent.click(screen.getByRole('button', { name: /close dialog/i }))
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })
})
