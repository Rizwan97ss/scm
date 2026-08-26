import type { ReactNode } from 'react'
import { Modal } from './Modal'
import { Button, type ButtonVariant } from './Button'

export interface ConfirmDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description?: string
  confirmLabel?: string
  cancelLabel?: string
  confirmVariant?: ButtonVariant
  isLoading?: boolean
  onConfirm: () => void
  /** Extra content rendered in the modal body — e.g. a "type to confirm" or password-recheck field for an especially destructive action. */
  children?: ReactNode
}

/** Use for any destructive or hard-to-undo action (delete, withdraw, deactivate). */
export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  confirmVariant = 'destructive',
  isLoading,
  onConfirm,
  children,
}: ConfirmDialogProps) {
  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title={title}
      description={description}
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isLoading}>
            {cancelLabel}
          </Button>
          <Button variant={confirmVariant} onClick={onConfirm} isLoading={isLoading}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      {children ?? <></>}
    </Modal>
  )
}
