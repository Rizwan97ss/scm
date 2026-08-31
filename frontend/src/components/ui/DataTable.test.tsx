import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { DataTable, type DataTableColumn } from './DataTable'

interface Row {
  id: number
  name: string
}

const columns: DataTableColumn<Row>[] = [{ key: 'name', header: 'Name', render: (row) => row.name }]

describe('DataTable', () => {
  it('shows skeleton rows while loading', () => {
    const { container } = render(<DataTable columns={columns} data={undefined} rowKey={(r) => r.id} isLoading />)
    expect(container.querySelectorAll('.animate-pulse').length).toBeGreaterThan(0)
  })

  it('shows the empty state when data is an empty array', () => {
    render(<DataTable columns={columns} data={[]} rowKey={(r) => r.id} emptyTitle="No rows yet" />)
    expect(screen.getByText('No rows yet')).toBeInTheDocument()
  })

  it('renders rows when data is present', () => {
    render(<DataTable columns={columns} data={[{ id: 1, name: 'Mathematics' }]} rowKey={(r) => r.id} />)
    expect(screen.getByText('Mathematics')).toBeInTheDocument()
  })

  it('shows a retry-able error state on isError, instead of the empty state or a blank body', () => {
    const onRetry = vi.fn()
    render(<DataTable columns={columns} data={undefined} rowKey={(r) => r.id} isError onRetry={onRetry} emptyTitle="No rows yet" />)

    expect(screen.getByText('Something went wrong')).toBeInTheDocument()
    expect(screen.queryByText('No rows yet')).not.toBeInTheDocument()
  })

  it('calls onRetry when the error state\'s Retry button is clicked', async () => {
    const user = userEvent.setup()
    const onRetry = vi.fn()
    render(<DataTable columns={columns} data={undefined} rowKey={(r) => r.id} isError onRetry={onRetry} />)

    await user.click(screen.getByRole('button', { name: 'Retry' }))
    expect(onRetry).toHaveBeenCalledOnce()
  })

  it('isError takes priority even if data happens to be an empty array', () => {
    render(<DataTable columns={columns} data={[]} rowKey={(r) => r.id} isError emptyTitle="No rows yet" />)
    expect(screen.getByText('Something went wrong')).toBeInTheDocument()
    expect(screen.queryByText('No rows yet')).not.toBeInTheDocument()
  })

  it('a row is not a keyboard target when onRowClick is not provided', () => {
    render(<DataTable columns={columns} data={[{ id: 1, name: 'Mathematics' }]} rowKey={(r) => r.id} />)
    expect(screen.queryByRole('button', { name: /Mathematics/ })).not.toBeInTheDocument()
  })

  it('a clickable row is keyboard-reachable and activates on Enter and Space', async () => {
    const user = userEvent.setup()
    const onRowClick = vi.fn()
    render(<DataTable columns={columns} data={[{ id: 1, name: 'Mathematics' }]} rowKey={(r) => r.id} onRowClick={onRowClick} />)

    const row = screen.getByRole('button', { name: 'Mathematics' })
    expect(row).toHaveAttribute('tabIndex', '0')

    row.focus()
    await user.keyboard('{Enter}')
    expect(onRowClick).toHaveBeenCalledTimes(1)

    await user.keyboard(' ')
    expect(onRowClick).toHaveBeenCalledTimes(2)
  })

  it('a clickable row still activates on a plain mouse click', async () => {
    const user = userEvent.setup()
    const onRowClick = vi.fn()
    render(<DataTable columns={columns} data={[{ id: 1, name: 'Mathematics' }]} rowKey={(r) => r.id} onRowClick={onRowClick} />)

    await user.click(screen.getByText('Mathematics'))
    expect(onRowClick).toHaveBeenCalledOnce()
  })

  it('hides a hideBelow column below its breakpoint but keeps it in the DOM (for larger screens), in the header, skeleton, and data cells alike', () => {
    const wideColumns: DataTableColumn<Row>[] = [
      { key: 'name', header: 'Name', render: (row) => row.name },
      { key: 'secondary', header: 'Secondary', hideBelow: 'md', render: () => 'extra detail' },
    ]

    const { rerender } = render(<DataTable columns={wideColumns} data={undefined} rowKey={(r) => r.id} isLoading />)
    const skeletonHeader = screen.getByRole('columnheader', { name: 'Secondary' })
    expect(skeletonHeader).toHaveClass('hidden', 'md:table-cell')

    rerender(<DataTable columns={wideColumns} data={[{ id: 1, name: 'Mathematics' }]} rowKey={(r) => r.id} />)
    expect(screen.getByRole('columnheader', { name: 'Secondary' })).toHaveClass('hidden', 'md:table-cell')
    const dataCell = screen.getByText('extra detail').closest('td')
    expect(dataCell).toHaveClass('hidden', 'md:table-cell')
    // A column with no hideBelow set is never hidden.
    expect(screen.getByRole('columnheader', { name: 'Name' })).not.toHaveClass('hidden')
  })
})
