import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { CheckCircle2, ExternalLink, FileText, Play, Plus, Trash2 } from 'lucide-react'
import { courseMaterialsApi } from '@/api/endpoints/courseMaterials'
import { VideoPlayerModal } from '../components/VideoPlayerModal'
import { resolveVideoEmbed } from '../utils/videoEmbed'
import { classSubjectTeachersApi, sectionsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useAuth } from '@/context/AuthContext'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import {
  Badge,
  Button,
  ConfirmDialog,
  DataTable,
  FormField,
  Input,
  Modal,
  Select,
  Textarea,
  type DataTableColumn,
} from '@/components/ui'
import { COURSE_MATERIAL_TYPES, COURSE_MATERIAL_TYPE_LABEL_KEYS } from '@/types/courseMaterials'
import type { CourseMaterial, CourseMaterialPayload, CourseMaterialType } from '@/types/courseMaterials'
import type { ApiError } from '@/api/client'

const EMPTY_FORM: CourseMaterialPayload = { section_id: 0, subject_id: 0, title: '', description: '', type: 'document', url: '', is_published: true }

function resourceUrl(material: CourseMaterial): string | null {
  if (material.url) return material.url
  return material.attachments[0]?.url ?? null
}

export function CourseMaterialListPage() {
  const { t } = useTranslation()
  const { user, hasRole } = useAuth()
  const { can } = usePermission()
  const queryClient = useQueryClient()
  const isStudent = hasRole('Student')
  const { setPage, queryParams } = usePagination('-created_at')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(courseMaterialsApi, queryKeys.courseMaterials, queryParams, 'Course material')

  const canCreate = can('course-materials.create')
  const canManage = canCreate || can('course-materials.edit')
  const { data: assignments } = useQuery({
    queryKey: ['class-subject-teachers', 'all'],
    queryFn: () => classSubjectTeachersApi.list(),
    enabled: canCreate,
  })
  const { data: sections } = useQuery({
    queryKey: queryKeys.sections({ per_page: 100 }),
    queryFn: () => sectionsApi.list({ per_page: 100 }),
    enabled: canCreate,
  })
  const sectionNameById = useMemo(() => new Map((sections?.data ?? []).map((s) => [s.id, s.name])), [sections])

  // Same restriction as HomeworkListPage: a Teacher/Class Teacher may only
  // add material to a class they're actually assigned to teach (enforced
  // server-side too — see CourseMaterialController's assertCanManage).
  const myAssignments = useMemo(() => {
    if (!assignments) return []
    return hasRole('Teacher', 'Class Teacher') ? assignments.filter((a) => a.teacher.id === user?.id) : assignments
  }, [assignments, hasRole, user])

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<CourseMaterial | null>(null)
  const [form, setForm] = useState<CourseMaterialPayload>(EMPTY_FORM)
  const [file, setFile] = useState<File | null>(null)
  const [deleting, setDeleting] = useState<CourseMaterial | null>(null)
  const [markingId, setMarkingId] = useState<number | null>(null)
  const [watching, setWatching] = useState<CourseMaterial | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setFile(null)
    setModalOpen(true)
  }

  function openEdit(material: CourseMaterial) {
    setEditing(material)
    setForm({
      section_id: material.section?.id ?? 0,
      subject_id: material.subject?.id ?? 0,
      title: material.title,
      description: material.description,
      type: material.type,
      url: material.url,
      is_published: material.is_published,
    })
    setFile(null)
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    const saved = editing ? await updateMutation.mutateAsync({ id: editing.id, payload: form }) : await createMutation.mutateAsync(form)
    if (file && saved) {
      try {
        await courseMaterialsApi.uploadAttachment(saved.id, file)
        queryClient.invalidateQueries({ queryKey: queryKeys.courseMaterials().slice(0, 1) })
      } catch (error) {
        toast.error((error as ApiError).message)
      }
    }
    setModalOpen(false)
  }

  async function openResource(material: CourseMaterial, markComplete = false) {
    const url = resourceUrl(material)
    if (isStudent) {
      setMarkingId(material.id)
      try {
        await courseMaterialsApi.markProgress(material.id, markComplete)
        queryClient.invalidateQueries({ queryKey: queryKeys.courseMaterials().slice(0, 1) })
      } catch (error) {
        toast.error((error as ApiError).message)
      } finally {
        setMarkingId(null)
      }
    }
    if (!url) return
    if (resolveVideoEmbed(url).kind !== 'none') {
      setWatching(material)
    } else {
      window.open(url, '_blank', 'noopener,noreferrer')
    }
  }

  const columns: DataTableColumn<CourseMaterial>[] = [
    { key: 'title', header: t('courseMaterials.titleLabel'), render: (row) => <span className="font-medium">{row.title}</span> },
    { key: 'subject', header: t('entities.subject'), render: (row) => row.subject?.name ?? '—' },
    { key: 'section', header: t('entities.section'), render: (row) => row.section?.name ?? '—' },
    { key: 'type', header: t('courseMaterials.typeLabel'), render: (row) => <Badge variant="outline">{t(COURSE_MATERIAL_TYPE_LABEL_KEYS[row.type])}</Badge> },
    isStudent
      ? {
          key: 'status',
          header: t('common.status'),
          render: (row) =>
            row.my_progress?.completed_at ? (
              <Badge variant="success">{t('courseMaterials.statusCompleted')}</Badge>
            ) : row.my_progress?.viewed_at ? (
              <Badge variant="info">{t('courseMaterials.statusViewed')}</Badge>
            ) : (
              <Badge variant="outline">{t('courseMaterials.statusNotStarted')}</Badge>
            ),
        }
      : { key: 'teacher', header: t('courseMaterials.teacherColumn'), render: (row) => row.teacher?.full_name ?? '—' },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => {
        const url = resourceUrl(row)
        const isVideo = resolveVideoEmbed(url).kind !== 'none'
        return (
          <div className="flex justify-end gap-2">
            {url && (
              <Button variant="outline" size="sm" onClick={() => openResource(row)} isLoading={markingId === row.id}>
                {isVideo ? <Play className="h-3.5 w-3.5" /> : <ExternalLink className="h-3.5 w-3.5" />}{' '}
                {isVideo ? t('courseMaterials.watchAction') : t('courseMaterials.openAction')}
              </Button>
            )}
            {isStudent && !row.my_progress?.completed_at && (
              <Button variant="outline" size="sm" onClick={() => openResource(row, true)} isLoading={markingId === row.id}>
                <CheckCircle2 className="h-3.5 w-3.5" /> {t('courseMaterials.markCompleteAction')}
              </Button>
            )}
            {canManage && (
              <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
                {t('common.edit')}
              </Button>
            )}
            {can('course-materials.delete') && (
              <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={t('common.deleteItem', { item: row.title })}>
                <Trash2 className="h-3.5 w-3.5" />
              </Button>
            )}
          </div>
        )
      },
    },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.course_materials')}
        description={isStudent ? t('courseMaterials.descriptionStudent') : t('courseMaterials.descriptionStaff')}
        actions={
          canCreate && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.courseMaterial') })}
            </Button>
          )
        }
      />
      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(r) => r.id}
        isLoading={listQuery.isLoading}
        isError={listQuery.isError}
        onRetry={listQuery.refetch}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        emptyTitle={t('common.noItemsYet', { items: t('nav.course_materials') })}
        emptyDescription={canCreate ? t('courseMaterials.emptyDescription') : undefined}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.courseMaterial') }) : t('common.newItem', { item: t('entities.courseMaterial') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('courseMaterials.classLabel')} htmlFor="assignment" required hint={t('courseMaterials.classHint')}>
            <Select
              id="assignment"
              value={form.section_id && form.subject_id ? `${form.section_id}-${form.subject_id}` : undefined}
              onValueChange={(value) => {
                const assignment = myAssignments.find((a) => `${a.section_id}-${a.subject.id}` === value)
                if (assignment) setForm((prev) => ({ ...prev, section_id: assignment.section_id, subject_id: assignment.subject.id }))
              }}
              options={myAssignments.map((a) => ({ value: `${a.section_id}-${a.subject.id}`, label: `${sectionNameById.get(a.section_id) ?? a.section_id} — ${a.subject.name}` }))}
              placeholder={t('courseMaterials.selectClassPlaceholder')}
            />
          </FormField>
          <FormField label={t('courseMaterials.titleLabel')} htmlFor="title" required>
            <Input id="title" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          </FormField>
          <FormField label={t('common.description')} htmlFor="description" hint={t('common.optional')}>
            <Textarea id="description" rows={3} value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </FormField>
          <FormField label={t('courseMaterials.typeLabel')} htmlFor="type" required>
            <Select
              id="type"
              value={form.type}
              onValueChange={(value) => setForm({ ...form, type: value as CourseMaterialType })}
              options={COURSE_MATERIAL_TYPES.map((type) => ({ value: type, label: t(COURSE_MATERIAL_TYPE_LABEL_KEYS[type]) }))}
            />
          </FormField>
          {(form.type === 'link' || form.type === 'video') && (
            <FormField label={t('courseMaterials.urlLabel')} htmlFor="url" required hint={t('courseMaterials.urlHint')}>
              <Input id="url" type="url" required value={form.url ?? ''} onChange={(e) => setForm({ ...form, url: e.target.value })} />
            </FormField>
          )}
          {form.type === 'document' && (
            <FormField label={t('courseMaterials.fileLabel')} htmlFor="file" hint={t('courseMaterials.fileHint')}>
              <input
                id="file"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.mp4,.mov,.webm"
                onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                className="block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border file:border-border file:bg-card file:px-3 file:py-1.5 file:text-sm"
              />
              {editing && editing.attachments.length > 0 && (
                <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                  <FileText className="h-3.5 w-3.5" /> {editing.attachments[0].file_name}
                </p>
              )}
            </FormField>
          )}
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.courseMaterial') })}
          </Button>
        </form>
      </Modal>

      {watching && (
        <VideoPlayerModal
          open={!!watching}
          onOpenChange={(open) => !open && setWatching(null)}
          title={watching.title}
          embed={resolveVideoEmbed(resourceUrl(watching))}
          fallbackUrl={resourceUrl(watching)}
        />
      )}

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('common.deleteConfirmTitle', { name: deleting?.title })}
        description={t('common.cannotBeUndone')}
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}
