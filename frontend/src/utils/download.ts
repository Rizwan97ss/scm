import { httpClient } from '@/api/client'

/**
 * Downloads an authenticated file (Excel export, import template, etc.) by
 * fetching it as a blob through our axios instance — a plain <a href> would
 * miss the session cookie/credentials handling httpClient already does.
 */
export async function downloadFile(url: string, filename: string): Promise<void> {
  const response = await httpClient.get(url, { responseType: 'blob' })
  const objectUrl = window.URL.createObjectURL(new Blob([response.data as BlobPart]))
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(objectUrl)
}
