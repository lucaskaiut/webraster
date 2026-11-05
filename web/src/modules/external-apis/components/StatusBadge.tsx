import { Badge } from '@/shared/design-system'
import type { ExternalApiRequest } from '../types'

export function StatusBadge({ request }: { request: Pick<ExternalApiRequest, 'succeeded' | 'status_code'> }) {
  if (request.status_code === null) {
    return <Badge variant="danger">Falha de conexão</Badge>
  }

  return <Badge variant={request.succeeded ? 'success' : 'danger'}>{request.status_code}</Badge>
}
