import { assetImageUrl } from '../../lib/assets'

/** Resolve device/template upload URLs (PHP uploads/ → /api/v1/uploads/). */
export function deviceUploadUrl(filename: string | null | undefined): string | null {
  if (!filename || !String(filename).trim()) return null
  const name = String(filename).trim()
  if (name.startsWith('http://') || name.startsWith('https://') || name.startsWith('/api/')) {
    return name
  }
  return `/api/v1/uploads/devices/${encodeURIComponent(name)}`
}

export function templateUploadUrl(filename: string | null | undefined): string | null {
  if (!filename || !String(filename).trim()) return null
  const name = String(filename).trim()
  if (name.startsWith('http://') || name.startsWith('https://') || name.startsWith('/api/')) {
    return name
  }
  // Backend serves both hyphen and underscore folder aliases via device_templates dir.
  return `/api/v1/uploads/device-templates/${encodeURIComponent(name)}`
}

export function defaultDeviceImageUrl(): string {
  return assetImageUrl('default-device.png', 'default-device.png')
}

/**
 * Image resolve order: device → template → default-device.png.
 * Pass includeDefault=false to get null so caller can show bi-box immediately.
 */
export function resolveDeviceImageUrl(opts: {
  imageUrl?: string | null
  image?: string | null
  templateImage?: string | null
  includeDefault?: boolean
}): string | null {
  if (opts.imageUrl) return opts.imageUrl
  const resolved = deviceUploadUrl(opts.image) ?? templateUploadUrl(opts.templateImage)
  if (resolved) return resolved
  if (opts.includeDefault === false) return null
  return defaultDeviceImageUrl()
}

export function faIconClass(icon: string | null | undefined): string {
  const raw = (icon || 'fa-server').trim()
  if (raw.startsWith('fa ') || raw.startsWith('fas ') || raw.startsWith('far ') || raw.startsWith('fab ')) {
    return raw
  }
  if (raw.startsWith('fa-')) return `fas ${raw}`
  return `fas fa-${raw}`
}
