import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileText, Trash2 } from 'lucide-react'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { Button, EmptyState, FileUpload, FormField, Select, Skeleton } from '@/components/ui'
import { formatDateTime } from '@/utils/formatDate'
import type { ApiError } from '@/api/client'

const COLLECTIONS = [
  { value: 'photo', label: 'Photo' },
  { value: 'birth_certificate', label: 'Birth Certificate' },
  { value: 'previous_report_card', label: 'Previous Report Card' },
  { value: 'transfer_certificate', label: 'Transfer Certificate' },
  { value: 'other', label: 'Other' },
]

export function StudentDocumentUploader({ studentId }: { studentId: number }) {
  const { can } = usePermission()
  const queryClient = useQueryClient()
  const [collection, setCollection] = useState('other')

  const { data: documents, isLoading } = useQuery({
    queryKey: queryKeys.studentDocuments(studentId),
    queryFn: () => studentsApi.documents(studentId),
  })

  const uploadMutation = useMutation({
    mutationFn: (file: File) => studentsApi.uploadDocument(studentId, collection, file),
    onSuccess: () => {
      toast.success('Document uploaded.')
      queryClient.invalidateQueries({ queryKey: queryKeys.studentDocuments(studentId) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const removeMutation = useMutation({
    mutationFn: (mediaId: number) => studentsApi.removeDocument(studentId, mediaId),
    onSuccess: () => {
      toast.success('Document removed.')
      queryClient.invalidateQueries({ queryKey: queryKeys.studentDocuments(studentId) })
    },
  })

  return (
    <div className="flex flex-col gap-4">
      {can('students.edit') && (
        <div className="flex flex-col gap-3 rounded-md border border-border p-4 sm:flex-row sm:items-end">
          <FormField label="Document type" className="sm:w-56">
            <Select value={collection} onValueChange={setCollection} options={COLLECTIONS} />
          </FormField>
          <div className="flex-1">
            <FileUpload accept="application/pdf,image/*" onFileSelect={(file) => uploadMutation.mutate(file)} disabled={uploadMutation.isPending} />
          </div>
        </div>
      )}

      {isLoading && <Skeleton className="h-24 w-full" />}

      {!isLoading && documents?.length === 0 && <EmptyState title="No documents uploaded yet" />}

      {!isLoading && (documents?.length ?? 0) > 0 && (
        <ul className="divide-y divide-border rounded-lg border border-border">
          {documents?.map((document) => (
            <li key={document.id} className="flex items-center justify-between gap-4 px-4 py-3">
              <a href={document.url} target="_blank" rel="noreferrer" className="flex items-center gap-3 text-sm hover:underline">
                <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                <span>
                  <span className="font-medium">{document.file_name}</span>
                  <span className="ml-2 text-xs capitalize text-muted-foreground">{document.collection.replace(/_/g, ' ')}</span>
                  <span className="block text-xs text-muted-foreground">{formatDateTime(document.created_at)}</span>
                </span>
              </a>
              {can('students.edit') && (
                <Button variant="outline" size="sm" onClick={() => removeMutation.mutate(document.id)} aria-label={`Remove ${document.file_name}`}>
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
