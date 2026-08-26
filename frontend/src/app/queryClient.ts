import { QueryCache, QueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import type { ApiError } from '@/api/client'

function isApiError(error: unknown): error is ApiError {
  return !!error && typeof error === 'object' && 'message' in error
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
    mutations: {
      onError: (error) => {
        if (!isApiError(error)) return
        toast.error(error.message)
      },
    },
  },
  queryCache: new QueryCache({
    onError: (error, query) => {
      if (!isApiError(error)) return
      const apiError: ApiError = error
      if (query.meta?.silent401 && apiError.status === 401) return
      if (query.meta?.silentError) return
      toast.error(apiError.message)
    },
  }),
})
