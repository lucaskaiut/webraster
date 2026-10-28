import { http } from '@/shared/api/http'

export const MAX_UPLOAD_BYTES = 10 * 1024 * 1024
export const MAX_UPLOAD_LABEL = '10 MB'

export interface UploadedFile {
  url: string
  path: string
}

/**
 * Envia um arquivo para o endpoint de upload e retorna URL e path públicos.
 */
export async function uploadFile(file: File, uploadUrl = '/uploads'): Promise<UploadedFile> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await http.post<{ data: UploadedFile }>(uploadUrl, formData)

  return response.data.data
}
