import { apiFormRequest, apiRequest } from '../../api/client'
import type {
  DcimTaxonomyEntry,
  TemplateDetail,
  TemplateSaveBody,
  TemplateSummary,
} from './types'

export async function fetchTemplates(): Promise<TemplateSummary[]> {
  return apiRequest<TemplateSummary[]>('/device-templates')
}

export async function fetchTemplate(id: number): Promise<TemplateDetail> {
  return apiRequest<TemplateDetail>(`/device-templates/${id}`)
}

export async function fetchDcimTaxonomy(): Promise<DcimTaxonomyEntry[]> {
  return apiRequest<DcimTaxonomyEntry[]>('/dcim-taxonomy')
}

export async function saveTemplate(
  id: number | null,
  body: TemplateSaveBody,
): Promise<TemplateDetail> {
  if (id) {
    return apiRequest<TemplateDetail>(`/device-templates/${id}`, { method: 'PATCH', body })
  }
  return apiRequest<TemplateDetail>('/device-templates', { method: 'POST', body })
}

export async function deleteTemplate(id: number): Promise<void> {
  await apiRequest(`/device-templates/${id}`, { method: 'DELETE' })
}

export async function uploadTemplateImage(file: File): Promise<string> {
  const fd = new FormData()
  fd.append('image', file)
  const uploaded = await apiFormRequest<{ filename: string; path: string; image: string }>(
    '/uploads/device-templates',
    fd,
  )
  return uploaded.filename || uploaded.image
}
