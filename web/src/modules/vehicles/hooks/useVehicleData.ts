import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  vehicleDataService,
  type VehicleDataConfigPayload,
} from '../services/vehicle-data.service'

export function useVehicleDataConfigQuery(provider?: string) {
  return useQuery({
    queryKey: [...queryKeys.vehicleData.config(), provider ?? 'default'],
    queryFn: () => vehicleDataService.getConfig(provider),
  })
}

export function useUpdateVehicleDataConfig() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: VehicleDataConfigPayload) => vehicleDataService.updateConfig(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.vehicleData.config() })
      toast.success('Configuração salva', 'As credenciais da consulta de placa foram salvas.')
    },
  })
}
