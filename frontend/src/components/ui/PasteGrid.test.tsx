import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { PasteGrid, type PasteGridProps } from './PasteGrid'

/** PasteGrid is a controlled component — this wrapper gives each test a real, working rows/onRowsChange pair without every test re-deriving that boilerplate. */
function ControlledGrid(props: Partial<PasteGridProps> & { columns: string[] }) {
  const [rows, setRows] = useState<string[][]>(props.rows ?? [props.columns.map(() => '')])
  return <PasteGrid {...props} rows={rows} onRowsChange={(next) => { setRows(next); props.onRowsChange?.(next) }} />
}

describe('PasteGrid', () => {
  it('renders a header cell per column and one input per cell', () => {
    render(<ControlledGrid columns={['name', 'code']} />)
    expect(screen.getByRole('columnheader', { name: 'name' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'code' })).toBeInTheDocument()
    expect(screen.getByLabelText('name, row 1')).toBeInTheDocument()
    expect(screen.getByLabelText('code, row 1')).toBeInTheDocument()
  })

  it('typing into a cell updates its value', async () => {
    const user = userEvent.setup()
    render(<ControlledGrid columns={['name', 'code']} />)

    await user.type(screen.getByLabelText('name, row 1'), 'Mathematics')

    expect(screen.getByLabelText('name, row 1')).toHaveValue('Mathematics')
  })

  it('adding a row appends a new empty row', async () => {
    const user = userEvent.setup()
    render(<ControlledGrid columns={['name', 'code']} />)

    await user.click(screen.getByRole('button', { name: /add row/i }))

    expect(screen.getByLabelText('name, row 2')).toBeInTheDocument()
  })

  it('cannot remove the last remaining row', () => {
    render(<ControlledGrid columns={['name', 'code']} />)
    expect(screen.getByRole('button', { name: 'Remove row 1' })).toBeDisabled()
  })

  it('removing a row drops exactly that row', async () => {
    const user = userEvent.setup()
    render(<ControlledGrid columns={['name']} rows={[['First'], ['Second']]} />)

    await user.click(screen.getByRole('button', { name: 'Remove row 1' }))

    expect(screen.queryByDisplayValue('First')).not.toBeInTheDocument()
    expect(screen.getByDisplayValue('Second')).toBeInTheDocument()
  })

  it('pasting tab/newline-separated text distributes across cells from the focused one', async () => {
    const user = userEvent.setup()
    render(<ControlledGrid columns={['name', 'code']} rows={[['', ''], ['', '']]} />)

    await user.click(screen.getByLabelText('name, row 1'))
    await user.paste('Mathematics\tMATH\nScience\tSCI')

    expect(screen.getByLabelText('name, row 1')).toHaveValue('Mathematics')
    expect(screen.getByLabelText('code, row 1')).toHaveValue('MATH')
    expect(screen.getByLabelText('name, row 2')).toHaveValue('Science')
    expect(screen.getByLabelText('code, row 2')).toHaveValue('SCI')
  })

  it('pasting more rows than currently exist grows the grid', async () => {
    const user = userEvent.setup()
    render(<ControlledGrid columns={['name']} rows={[['']]} />)

    await user.click(screen.getByLabelText('name, row 1'))
    await user.paste('First\nSecond\nThird')

    expect(screen.getByLabelText('name, row 3')).toHaveValue('Third')
  })

  it('a plain single-value paste is not intercepted as a multi-cell paste', async () => {
    const user = userEvent.setup()
    render(<ControlledGrid columns={['name', 'code']} />)

    await user.click(screen.getByLabelText('name, row 1'))
    await user.paste('Mathematics')

    expect(screen.getByLabelText('name, row 1')).toHaveValue('Mathematics')
    expect(screen.getByLabelText('code, row 1')).toHaveValue('')
  })

  it('marks a cell with a mapped error as invalid and shows the message as a title', () => {
    const cellErrors = new Map([['0:code', 'This code already exists.']])
    render(<ControlledGrid columns={['name', 'code']} cellErrors={cellErrors} />)

    const codeCell = screen.getByLabelText('code, row 1')
    expect(codeCell).toHaveAttribute('aria-invalid', 'true')
    expect(codeCell).toHaveAttribute('title', 'This code already exists.')
    expect(screen.getByLabelText('name, row 1')).toHaveAttribute('aria-invalid', 'false')
  })

  it('disables every input and button when disabled', () => {
    render(<ControlledGrid columns={['name']} disabled />)
    expect(screen.getByLabelText('name, row 1')).toBeDisabled()
    expect(screen.getByRole('button', { name: /add row/i })).toBeDisabled()
  })

  it('calls onRowsChange, not a stale rows reference, when editing', async () => {
    const user = userEvent.setup()
    const onRowsChange = vi.fn()
    render(<ControlledGrid columns={['name']} onRowsChange={onRowsChange} />)

    await user.type(screen.getByLabelText('name, row 1'), 'X')

    expect(onRowsChange).toHaveBeenCalledWith([['X']])
  })
})
