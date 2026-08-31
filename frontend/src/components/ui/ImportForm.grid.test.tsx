import { describe, expect, it, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { renderWithProviders } from '@/testing/testUtils'
import { server } from '@/testing/mswServer'
import { apiUrl } from '@/api/client'
import { ImportForm } from './ImportForm'
import type { ImportResult } from '@/types/import'

const apiV1 = `${apiUrl}/v1`

/**
 * Focused on the one genuinely subtle piece of new logic living directly in
 * ImportForm (not covered by PasteGrid's or csv.ts's own unit tests): a
 * backend Failure's `row` is PhpSpreadsheet's 1-indexed-including-header
 * row number, so gridRows[0] (the first data row) is reported as row 2 —
 * get that mapping wrong and error highlighting lands on the wrong cell.
 */
describe('ImportForm grid mode', () => {
  it('maps a preview failure onto the correct grid cell, and clears it once fixed', async () => {
    const user = userEvent.setup()
    const onImport = vi.fn<(file: File, dryRun: boolean) => Promise<ImportResult>>().mockResolvedValueOnce({
      imported_count: 0,
      failed_count: 1,
      failures: [{ row: 2, attribute: 'code', errors: ['The code has already been taken.'] }],
      dry_run: true,
    })

    renderWithProviders(
      <ImportForm
        entityLabel="department"
        templateUrl="/departments/import/template"
        templateFilename="department-import-template.xlsx"
        description="test"
        onImport={onImport}
        columns={['name', 'code']}
      />
    )

    await user.click(screen.getByRole('button', { name: 'Paste data' }))
    await user.type(await screen.findByLabelText('name, row 1'), 'Mathematics')
    await user.type(screen.getByLabelText('code, row 1'), 'MATSCI')
    await user.click(screen.getByRole('button', { name: 'Preview Import' }))

    await waitFor(() => expect(screen.getByLabelText('code, row 1')).toHaveAttribute('aria-invalid', 'true'))
    expect(screen.getByLabelText('code, row 1')).toHaveAttribute('title', 'The code has already been taken.')
    expect(screen.getByLabelText('name, row 1')).toHaveAttribute('aria-invalid', 'false')

    onImport.mockResolvedValueOnce({ imported_count: 1, failed_count: 0, failures: [], dry_run: true })
    await user.click(screen.getByRole('button', { name: 'Re-check' }))

    await waitFor(() => expect(screen.getByLabelText('code, row 1')).toHaveAttribute('aria-invalid', 'false'))
  })

  it('sends the grid contents as a CSV file, not the original upload path', async () => {
    const user = userEvent.setup()
    const onImport = vi.fn<(file: File, dryRun: boolean) => Promise<ImportResult>>().mockResolvedValue({
      imported_count: 1,
      failed_count: 0,
      failures: [],
      dry_run: true,
    })

    renderWithProviders(
      <ImportForm
        entityLabel="department"
        templateUrl="/departments/import/template"
        templateFilename="department-import-template.xlsx"
        description="test"
        onImport={onImport}
        columns={['name', 'code']}
      />
    )

    await user.click(screen.getByRole('button', { name: 'Paste data' }))
    await user.type(await screen.findByLabelText('name, row 1'), 'Mathematics')
    await user.type(screen.getByLabelText('code, row 1'), 'MATSCI')
    await user.click(screen.getByRole('button', { name: 'Preview Import' }))

    await waitFor(() => expect(onImport).toHaveBeenCalled())
    const [file, dryRun] = onImport.mock.calls[0]
    expect(dryRun).toBe(true)
    expect(file.type).toBe('text/csv')
    expect(await file.text()).toBe('name,code\nMathematics,MATSCI')
  })

  it('mapping a file and clicking Continue lands in grid mode with the mapped, reordered rows pre-filled', async () => {
    const user = userEvent.setup()
    server.use(
      http.post(`${apiV1}/import-preview`, () =>
        HttpResponse.json({
          success: true,
          message: null,
          data: { headers: ['Dept Code', 'Department Name'], rows: [['MATH', 'Mathematics']], truncated: false },
        })
      )
    )
    const onImport = vi.fn<(file: File, dryRun: boolean) => Promise<ImportResult>>().mockResolvedValue({
      imported_count: 1,
      failed_count: 0,
      failures: [],
      dry_run: true,
    })

    const { container } = renderWithProviders(
      <ImportForm
        entityLabel="department"
        templateUrl="/departments/import/template"
        templateFilename="department-import-template.xlsx"
        description="test"
        onImport={onImport}
        columns={['name', 'code']}
      />
    )

    await user.click(screen.getByRole('button', { name: 'Upload & map columns' }))
    const fileInput = await waitFor(() => {
      const input = container.querySelector('input[type="file"]')
      if (!input) throw new Error('file input not mounted yet')
      return input as HTMLInputElement
    })
    await user.upload(fileInput, new File(['irrelevant'], 'depts.csv', { type: 'text/csv' }))

    await user.selectOptions(await screen.findByLabelText('Maps "Dept Code" to'), 'code')
    await user.selectOptions(screen.getByLabelText('Maps "Department Name" to'), 'name')
    await user.click(screen.getByRole('button', { name: /continue with/i }))

    // Landed in grid mode (not still on the mapping step), pre-filled in canonical name/code order despite the file having code first.
    expect(await screen.findByLabelText('name, row 1')).toHaveValue('Mathematics')
    expect(screen.getByLabelText('code, row 1')).toHaveValue('MATH')
  })
})
