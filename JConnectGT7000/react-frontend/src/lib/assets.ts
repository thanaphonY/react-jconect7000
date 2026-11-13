/** PHP `iaBase()/assets/images/{filename}` equivalent via backend asset route. */
export function assetImageUrl(filename: string | null | undefined, fallback = 'user.png'): string {
  const name = (filename && filename.trim()) || fallback
  return `/api/v1/assets/images/${encodeURIComponent(name)}`
}

export type Branding = {
  website: string
  logo: string
  favicon: string
  register_allowed: string
}

export function applyFavicon(favicon: string | null | undefined) {
  const href = assetImageUrl(favicon || 'favicon.png', 'favicon.png')
  let link = document.querySelector<HTMLLinkElement>("link[rel='icon']")
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }
  link.type = 'image/x-icon'
  link.href = href
}
