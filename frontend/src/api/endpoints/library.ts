import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { Book, BookIssue, BookPayload, IssueBookPayload } from '@/types/library'

export const booksApi = {
  ...createCrudEndpoints<Book, BookPayload>('books'),
  issue: async (bookId: number, payload: IssueBookPayload): Promise<BookIssue> => {
    const { data } = await httpClient.post<ApiResponse<BookIssue>>(`/books/${bookId}/issue`, payload)
    return data.data
  },
}

export const bookIssuesApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<BookIssue>> => {
    const { data } = await httpClient.get<PaginatedResponse<BookIssue>>('/book-issues', { params })
    return data
  },
  returnBook: async (id: number): Promise<BookIssue> => {
    const { data } = await httpClient.post<ApiResponse<BookIssue>>(`/book-issues/${id}/return`)
    return data.data
  },
}
