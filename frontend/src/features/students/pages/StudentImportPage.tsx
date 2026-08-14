import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Download } from 'lucide-react'
import { toast } from 'sonner'
import { studentsApi } from '@/api/endpoints/students'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, FileUpload } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { downloadFile } from '@/utils/download'
import type { StudentImportResult } from '@/types/student'
import { formatApiError, type ApiError } from '@/api/client'

export function StudentImportPage() {
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [result, setResult] = useState<StudentImportResult | null>(null)

  const importMutation = useMutation({
    mutationFn: (file: File) => studentsApi.import(file),
    onSuccess: (data) => {
      setResult(data)
      toast.success(`${data.imported_count} student(s) imported.`)
    },
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })

  return (
    <div>
      <PageHeader
        title="Bulk Import Students"
        breadcrumbs={[{ label: 'Students', to: routePaths.students }, { label: 'Import' }]}
        actions={
          <Button variant="outline" onClick={() => downloadFile(studentsApi.importTemplateUrl, 'student-import-template.xlsx')}>
            <Download className="h-4 w-4" /> Download Template
          </Button>
        }
      />

      <div className="max-w-xl">
        <p className="mb-4 text-sm text-muted-foreground">
          Upload an Excel (.xlsx) or CSV file following the template. Grade level and section codes must match existing records exactly.
        </p>

        <FileUpload
          accept=".xlsx,.xls,.csv"
          selectedFileName={selectedFile?.name}
          onFileSelect={(file) => {
            setSelectedFile(file)
            setResult(null)
          }}
          onClear={() => {
            setSelectedFile(null)
            setResult(null)
          }}
          disabled={importMutation.isPending}
        />

        {selectedFile && !result && (
          <Button className="mt-4" onClick={() => importMutation.mutate(selectedFile)} isLoading={importMutation.isPending}>
            Import students
          </Button>
        )}

        {result && (
          <div className="mt-6 flex flex-col gap-3">
            <div className="flex gap-2">
              <Badge variant="success">{result.imported_count} imported</Badge>
              {result.failed_count > 0 && <Badge variant="destructive">{result.failed_count} failed</Badge>}
            </div>
            {result.failures.length > 0 && (
              <div className="rounded-md border border-border">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                    <tr>
                      <th className="p-2 text-left">Row</th>
                      <th className="p-2 text-left">Field</th>
                      <th className="p-2 text-left">Error</th>
                    </tr>
                  </thead>
                  <tbody>
                    {result.failures.map((failure, index) => (
                      <tr key={index} className="border-t border-border">
                        <td className="p-2">{failure.row}</td>
                        <td className="p-2">{failure.attribute}</td>
                        <td className="p-2">{failure.errors.join(', ')}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
