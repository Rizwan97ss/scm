import { useRef, useState, type DragEvent } from 'react'
import { UploadCloud, FileText, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/utils/cn'

export interface FileUploadProps {
  onFileSelect: (file: File) => void
  accept?: string
  maxSizeMb?: number
  selectedFileName?: string | null
  onClear?: () => void
  disabled?: boolean
}

export function FileUpload({ onFileSelect, accept, maxSizeMb = 10, selectedFileName, onClear, disabled }: FileUploadProps) {
  const { t } = useTranslation()
  const inputRef = useRef<HTMLInputElement>(null)
  const [isDragging, setIsDragging] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function handleFile(file: File | undefined) {
    if (!file) return
    if (file.size > maxSizeMb * 1024 * 1024) {
      setError(t('common.fileUpload.tooLarge', { maxSizeMb }))
      return
    }
    setError(null)
    onFileSelect(file)
  }

  function handleDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault()
    setIsDragging(false)
    if (disabled) return
    handleFile(event.dataTransfer.files[0])
  }

  if (selectedFileName) {
    return (
      <div className="flex items-center justify-between gap-3 rounded-md border border-border bg-muted/50 px-3 py-2.5">
        <span className="flex items-center gap-2 truncate text-sm">
          <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
          <span className="truncate">{selectedFileName}</span>
        </span>
        {onClear && (
          <button type="button" onClick={onClear} className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={t('common.fileUpload.removeFile')}>
            <X className="h-4 w-4" />
          </button>
        )}
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-1.5">
      <div
        onDragOver={(e) => {
          e.preventDefault()
          setIsDragging(true)
        }}
        onDragLeave={() => setIsDragging(false)}
        onDrop={handleDrop}
        onClick={() => !disabled && inputRef.current?.click()}
        className={cn(
          'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-border px-4 py-8 text-center transition-colors',
          isDragging && 'border-primary bg-primary/5',
          disabled && 'cursor-not-allowed opacity-50'
        )}
      >
        <UploadCloud className="h-6 w-6 text-muted-foreground" />
        <p className="text-sm text-muted-foreground">
          <span className="font-medium text-primary">{t('common.fileUpload.clickToUpload')}</span> {t('common.fileUpload.orDragAndDrop')}
        </p>
        <input
          ref={inputRef}
          type="file"
          accept={accept}
          disabled={disabled}
          className="hidden"
          onChange={(e) => handleFile(e.target.files?.[0])}
        />
      </div>
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  )
}
