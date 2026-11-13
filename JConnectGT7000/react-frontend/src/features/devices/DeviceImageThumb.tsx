import { useState, type CSSProperties, type ReactNode } from 'react'
import { resolveDeviceImageUrl } from './urls'
import './metricBuilder.css'

type Props = {
  imageUrl?: string | null
  image?: string | null
  templateImage?: string | null
  alt?: string
  size?: number
  style?: CSSProperties
  className?: string
  fallback?: ReactNode
  /** List-view hover zoom preview (PHP .device-image-table). */
  hoverPreview?: boolean
  /** When false, skip default-device.png and show bi-box if no device/template image. */
  includeDefault?: boolean
}

export function DeviceImageThumb({
  imageUrl,
  image,
  templateImage,
  alt = 'device',
  size = 48,
  style,
  className,
  fallback,
  hoverPreview = false,
  includeDefault = true,
}: Props) {
  const [failed, setFailed] = useState(false)
  const src = failed
    ? null
    : resolveDeviceImageUrl({ imageUrl, image, templateImage, includeDefault })

  const fallbackNode = (
    <div
      className={className}
      style={{
        width: size,
        height: size,
        display: 'grid',
        placeItems: 'center',
        background: '#f5f5f5',
        borderRadius: 6,
        color: '#666',
        fontSize: size * 0.45,
        ...style,
      }}
    >
      {fallback ?? <i className="bi bi-box" />}
    </div>
  )

  if (!src) return fallbackNode

  const img = (
    <img
      className={hoverPreview ? undefined : className}
      src={src}
      alt={alt}
      style={{
        width: size,
        height: size,
        objectFit: 'contain',
        borderRadius: 6,
        background: '#fafafa',
        ...style,
      }}
      onError={() => setFailed(true)}
    />
  )

  if (!hoverPreview) return img

  return (
    <div className={`device-image-table ${className || ''}`}>
      {img}
      <div
        className="device-image-hover-preview"
        style={{ backgroundImage: `url(${src})` }}
      />
    </div>
  )
}
