export type VideoEmbed =
  | { kind: 'youtube'; embedUrl: string }
  | { kind: 'vimeo'; embedUrl: string }
  | { kind: 'file'; url: string }
  | { kind: 'none' }

const VIDEO_FILE_EXTENSIONS = ['.mp4', '.webm', '.ogg', '.ogv', '.mov', '.m4v']

/**
 * Recognizes YouTube/Vimeo links and direct video file URLs (including
 * teacher-uploaded attachments) so the player can embed them in-app instead
 * of just opening a new tab. Anything else (an arbitrary external link) is
 * `{ kind: 'none' }` — most sites block being framed via X-Frame-Options, so
 * those stay as an "open externally" link rather than a broken embed.
 */
export function resolveVideoEmbed(url: string | null): VideoEmbed {
  if (!url) return { kind: 'none' }

  let parsed: URL
  try {
    parsed = new URL(url)
  } catch {
    return { kind: 'none' }
  }

  const host = parsed.hostname.replace(/^www\./, '').replace(/^m\./, '')
  const path = parsed.pathname.toLowerCase()

  if (host === 'youtube.com') {
    const id = parsed.pathname === '/watch' ? parsed.searchParams.get('v') : parsed.pathname.split('/').filter(Boolean).pop()
    if (id) return { kind: 'youtube', embedUrl: `https://www.youtube-nocookie.com/embed/${id}` }
  }
  if (host === 'youtu.be') {
    const id = parsed.pathname.split('/').filter(Boolean)[0]
    if (id) return { kind: 'youtube', embedUrl: `https://www.youtube-nocookie.com/embed/${id}` }
  }
  if (host === 'vimeo.com') {
    const id = parsed.pathname.split('/').filter(Boolean)[0]
    if (id && /^\d+$/.test(id)) return { kind: 'vimeo', embedUrl: `https://player.vimeo.com/video/${id}` }
  }
  if (VIDEO_FILE_EXTENSIONS.some((ext) => path.endsWith(ext))) {
    return { kind: 'file', url }
  }

  return { kind: 'none' }
}
