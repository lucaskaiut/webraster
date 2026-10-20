import { History, MapPin } from 'lucide-react'
import { Alert, Badge, Card, CardContent, CardHeader, Skeleton } from '@/shared/design-system'
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import type { EquipmentStockMovementType } from '@/shared/types/models'
import { useEquipmentStockMovementsQuery } from '../hooks/useEquipments'

const MOVEMENT_VARIANTS: Record<
  EquipmentStockMovementType,
  'neutral' | 'primary' | 'success' | 'warning' | 'danger'
> = {
  entry: 'success',
  transfer: 'primary',
  exit: 'warning',
  installation: 'warning',
  return: 'success',
}

export function EquipmentStockMovementsCard({ equipmentId }: { equipmentId: string }) {
  const query = useEquipmentStockMovementsQuery(equipmentId)
  const movements = query.data ?? []

  return (
    <Card>
      <CardHeader
        title="Movimentações de estoque"
        description="Entradas, transferências, instalações e retornos registrados para este equipamento."
      />
      <CardContent>
        {query.isPending && (
          <div className="space-y-3">
            <Skeleton className="h-16" />
            <Skeleton className="h-16" />
            <Skeleton className="h-16" />
          </div>
        )}

        {query.isError && (
          <Alert variant="danger" title="Não foi possível carregar as movimentações">
            Tente novamente ao recarregar a página.
          </Alert>
        )}

        {!query.isPending && !query.isError && movements.length === 0 && (
          <div className="flex flex-col items-center justify-center gap-2 rounded-xl bg-surface-2/60 px-4 py-8 text-center">
            <History className="size-6 text-subtle" aria-hidden="true" />
            <p className="text-sm font-medium text-foreground">Nenhuma movimentação registrada</p>
            <p className="text-[13px] text-muted">
              As movimentações aparecem aqui quando o equipamento entra ou é transferido de estoque,
              é instalado em um veículo ou retorna.
            </p>
          </div>
        )}

        {movements.length > 0 && (
          <div className="space-y-2">
            {movements.map((movement) => (
              <div key={movement.id} className="rounded-xl bg-surface-2/60 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={MOVEMENT_VARIANTS[movement.type]}>
                      {movement.type_label}
                    </Badge>
                    <span className="text-xs text-muted">
                      {formatDateTimeWithSeconds(movement.occurred_at)}
                    </span>
                  </div>
                  <span className="text-xs text-muted">
                    {movement.moved_by?.name ?? 'Sistema'}
                  </span>
                </div>

                <p className="mt-2 text-sm text-foreground">{movement.description}</p>

                {(movement.from_stock || movement.to_stock) && (
                  <p className="mt-1 flex flex-wrap items-center gap-1 text-xs text-muted">
                    <MapPin className="size-3.5" aria-hidden="true" />
                    {movement.from_stock?.name ?? 'Fora do estoque'}
                    <span aria-hidden="true">→</span>
                    {movement.to_stock?.name ?? 'Fora do estoque'}
                  </p>
                )}
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
