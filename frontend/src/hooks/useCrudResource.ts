import { useMutation, useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query'
import { toast } from 'sonner'
import { formatApiError, type ApiError } from '@/api/client'
import type { ListQueryParams, PaginatedResponse } from '@/types/api'

export interface CrudApi<TResource, TPayload> {
  list: (params?: ListQueryParams) => Promise<PaginatedResponse<TResource>>
  create: (payload: TPayload) => Promise<TResource>
  update: (id: number, payload: Partial<TPayload>) => Promise<TResource>
  remove: (id: number) => Promise<void>
}

/**
 * Wires up the list query + create/update/delete mutations for a standard
 * CRUD resource, with cache invalidation and toast feedback handled once
 * here instead of duplicated across every admin list page.
 */
export function useCrudResource<TResource, TPayload>(
  api: CrudApi<TResource, TPayload>,
  queryKey: (params?: ListQueryParams) => QueryKey,
  params: ListQueryParams | undefined,
  resourceLabel: string
) {
  const queryClient = useQueryClient()

  const listQuery = useQuery({
    queryKey: queryKey(params),
    queryFn: () => api.list(params),
    placeholderData: (previous) => previous,
  })

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: queryKey(params).slice(0, 1) })
  }

  // httpClient's response interceptor normalizes failures into ApiError but
  // doesn't toast them itself (see client.ts) — every mutation needs its own
  // onError, or a failed create/update/delete fails completely silently: no
  // toast, and (since callers typically do `await mutateAsync(...); close()`
  // with no try/catch of their own) the modal is left open with zero visible
  // indication anything went wrong. Caught the hard way once a caller's own
  // page-wide text assertion in a smoke test was satisfied by a still-open
  // form's own field value instead of a real persisted record.
  function onError(error: unknown) {
    toast.error(formatApiError(error as ApiError))
  }

  const createMutation = useMutation({
    mutationFn: api.create,
    onSuccess: () => {
      toast.success(`${resourceLabel} created.`)
      invalidate()
    },
    onError,
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<TPayload> }) => api.update(id, payload),
    onSuccess: () => {
      toast.success(`${resourceLabel} updated.`)
      invalidate()
    },
    onError,
  })

  const removeMutation = useMutation({
    mutationFn: api.remove,
    onSuccess: () => {
      toast.success(`${resourceLabel} deleted.`)
      invalidate()
    },
    onError,
  })

  return { listQuery, createMutation, updateMutation, removeMutation }
}
