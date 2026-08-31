import { describe, expect, it, vi } from 'vitest'
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { ImportFileMapper } from './ImportFileMapper'
import { renderWithProviders } from '@/testing/testUtils'
import { server } from '@/testing/mswServer'
import { apiUrl } from '@/api/client'

const apiV1 = `${apiUrl}/v1`

function mockPreviewResponse(body: { headers: string[]; rows: string[][]; truncated?: boolean }) {
  server.use(http.post(`${apiV1}/import-preview`, () => HttpResponse.json({ success: true, message: null, data: { truncated: false, ...body } })))
}

async function uploadFile(container: HTMLElement, name = 'my-file.csv') {
  const input = container.querySelector('input[type="file"]') as HTMLInputElement
  const file = new File(['irrelevant — the server response is what drives the test'], name, { type: 'text/csv' })
  await userEvent.upload(input, file)
}

describe('ImportFileMapper', () => {
  it('shows a mapping row per detected header, pre-filled by the fuzzy matcher', async () => {
    mockPreviewResponse({ headers: ['Department Name', 'Dept Code'], rows: [['Mathematics', 'MATH']] })
    const { container } = renderWithProviders(<ImportFileMapper columns={['name', 'code']} onMapped={vi.fn()} />)

    await uploadFile(container)

    expect(await screen.findByLabelText('Maps "Department Name" to')).toHaveValue('name')
    expect(screen.getByLabelText('Maps "Dept Code" to')).toHaveValue('code')
  })

  it('skips a fully blank header column entirely', async () => {
    mockPreviewResponse({ headers: ['Name', ''], rows: [['Mathematics', '']] })
    const { container } = renderWithProviders(<ImportFileMapper columns={['name']} onMapped={vi.fn()} />)

    await uploadFile(container)

    expect(await screen.findByLabelText('Maps "Name" to')).toBeInTheDocument()
    expect(screen.queryAllByRole('combobox')).toHaveLength(1)
  })

  it('an unrecognized header defaults to "Don\'t import" rather than guessing wrong', async () => {
    mockPreviewResponse({ headers: ['Some Unrelated Column'], rows: [['x']] })
    const { container } = renderWithProviders(<ImportFileMapper columns={['name', 'code']} onMapped={vi.fn()} />)

    await uploadFile(container)

    expect(await screen.findByLabelText('Maps "Some Unrelated Column" to')).toHaveValue('__skip__')
  })

  it('confirming reorders and filters rows to match the canonical column order, dropping skipped columns', async () => {
    const onMapped = vi.fn()
    // File order is code, name — canonical order is name, code — proves the mapper reorders, not just passes through.
    mockPreviewResponse({
      headers: ['Dept Code', 'Department Name', 'Irrelevant Notes'],
      rows: [
        ['MATH', 'Mathematics', 'ignored'],
        ['SCI', 'Science', 'ignored'],
      ],
    })
    const { container } = renderWithProviders(<ImportFileMapper columns={['name', 'code']} onMapped={onMapped} />)

    await uploadFile(container)
    await screen.findByLabelText('Maps "Dept Code" to')
    await userEvent.click(screen.getByRole('button', { name: /continue with/i }))

    expect(onMapped).toHaveBeenCalledWith([
      ['Mathematics', 'MATH'],
      ['Science', 'SCI'],
    ])
  })

  it('changing a mapping by hand overrides the fuzzy guess', async () => {
    const onMapped = vi.fn()
    mockPreviewResponse({ headers: ['A', 'B'], rows: [['first', 'second']] })
    const { container } = renderWithProviders(<ImportFileMapper columns={['name', 'code']} onMapped={onMapped} />)

    await uploadFile(container)
    await userEvent.selectOptions(await screen.findByLabelText('Maps "A" to'), 'code')
    await userEvent.selectOptions(screen.getByLabelText('Maps "B" to'), 'name')
    await userEvent.click(screen.getByRole('button', { name: /continue with/i }))

    expect(onMapped).toHaveBeenCalledWith([['second', 'first']])
  })

  it('the confirm button is disabled until at least one column is mapped', async () => {
    mockPreviewResponse({ headers: ['Unrelated'], rows: [['x']] })
    const { container } = renderWithProviders(<ImportFileMapper columns={['name']} onMapped={vi.fn()} />)

    await uploadFile(container)

    expect(await screen.findByRole('button', { name: /continue with 0 mapped/i })).toBeDisabled()
  })

  it('"Choose a different file" returns to the upload step', async () => {
    mockPreviewResponse({ headers: ['Name'], rows: [['Mathematics']] })
    const { container } = renderWithProviders(<ImportFileMapper columns={['name']} onMapped={vi.fn()} />)

    await uploadFile(container)
    await screen.findByLabelText('Maps "Name" to')
    await userEvent.click(screen.getByRole('button', { name: /choose a different file/i }))

    expect(screen.queryByLabelText('Maps "Name" to')).not.toBeInTheDocument()
    expect(container.querySelector('input[type="file"]')).toBeInTheDocument()
  })
})
