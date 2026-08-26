export interface SearchResultItem {
  id: number
  label: string
  sublabel: string | null
}

export type SearchResultCategory = 'students' | 'guardians' | 'staff' | 'books' | 'invoices'

export interface SearchResponse {
  query: string
  results: Partial<Record<SearchResultCategory, SearchResultItem[]>>
}
