import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { SearchInput } from './SearchInput'

function ControlledSearchInput() {
  const [value, setValue] = useState('')
  return <SearchInput value={value} onChange={setValue} placeholder="Search…" />
}

describe('SearchInput', () => {
  it('renders the placeholder and calls onChange as the user types', async () => {
    const user = userEvent.setup()
    const onChange = vi.fn()
    render(<SearchInput value="" onChange={onChange} placeholder="Search by name…" />)

    await user.type(screen.getByPlaceholderText('Search by name…'), 'Sam')

    expect(onChange).toHaveBeenCalledWith('S')
    expect(onChange).toHaveBeenCalledWith('a')
    expect(onChange).toHaveBeenCalledWith('m')
  })

  it('shows no clear button when the value is empty', () => {
    render(<SearchInput value="" onChange={vi.fn()} />)
    expect(screen.queryByRole('button', { name: 'Clear search' })).not.toBeInTheDocument()
  })

  it('shows a clear button once there is a value, and clicking it empties the field', async () => {
    const user = userEvent.setup()
    render(<ControlledSearchInput />)

    await user.type(screen.getByPlaceholderText('Search…'), 'Sam')
    expect(screen.getByRole('button', { name: 'Clear search' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Clear search' }))

    expect(screen.getByPlaceholderText('Search…')).toHaveValue('')
    expect(screen.queryByRole('button', { name: 'Clear search' })).not.toBeInTheDocument()
  })
})
