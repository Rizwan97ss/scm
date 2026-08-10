import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileText } from 'lucide-react'
import { homeworkApi } from '@/api/endpoints/homework'
import { queryKeys } from '@/api/queryKeys'
import { Badge, Button, FileUpload, FormField, Modal, Textarea } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import { HOMEWORK_SUBMISSION_STATUS_LABELS } from '@/types/homework'
import type { Homework } from '@/types/homework'
import type { ApiError } from '@/api/client'

export function HomeworkSubmitModal({ homework, open, onOpenChange }: { homework: Homework; open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [content, setContent] = useState(homework.my_submission?.content ?? '')
  const [file, setFile] = useState<File | null>(null)

  const submitMutation = useMutation({
    mutationFn: () => homeworkApi.submit(homework.id, { content, file }),
    onSuccess: () => {
      toast.success('Homework submitted.')
      queryClient.invalidateQueries({ queryKey: queryKeys.homework().slice(0, 1) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const submission = homework.my_submission

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={homework.title} description={`Due ${formatDate(homework.due_date)}${homework.max_score ? ` · Max score: ${homework.max_score}` : ''}`}>
      <div className="flex flex-col gap-4">
        {homework.description && <p className="text-sm text-muted-foreground">{homework.description}</p>}

        {homework.attachments.length > 0 && (
          <div className="flex flex-col gap-1.5">
            <p className="text-sm font-medium">Instructions</p>
            {homework.attachments.map((attachment) => (
              <a key={attachment.id} href={attachment.url} target="_blank" rel="noreferrer" className="flex items-center gap-2 text-sm text-primary hover:underline">
                <FileText className="h-4 w-4 shrink-0" /> {attachment.file_name}
              </a>
            ))}
          </div>
        )}

        {submission?.status === 'graded' && (
          <div className="rounded-md border border-border bg-muted/50 p-3">
            <div className="mb-1 flex items-center gap-2">
              <Badge variant="success">{HOMEWORK_SUBMISSION_STATUS_LABELS[submission.status]}</Badge>
              {submission.score !== null && <span className="text-sm font-medium">{submission.score}{homework.max_score ? ` / ${homework.max_score}` : ''}</span>}
            </div>
            {submission.feedback && <p className="text-sm text-muted-foreground">{submission.feedback}</p>}
          </div>
        )}

        <FormField label="Your answer" htmlFor="content">
          <Textarea id="content" rows={4} value={content} onChange={(e) => setContent(e.target.value)} placeholder="Type your answer or notes here (optional if you're attaching a file)" />
        </FormField>

        <FormField label="Attachment" htmlFor="file" hint="Optional">
          <FileUpload
            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
            onFileSelect={setFile}
            selectedFileName={file?.name ?? (submission?.attachments[0]?.file_name && !file ? submission.attachments[0].file_name : null)}
            onClear={() => setFile(null)}
          />
        </FormField>

        <Button onClick={() => submitMutation.mutate()} isLoading={submitMutation.isPending} className="mt-2">
          {submission ? 'Resubmit' : 'Submit homework'}
        </Button>
      </div>
    </Modal>
  )
}
