import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  vehiclesService,
  type AssignmentPayload,
  type VehicleListParams,
  type VehiclePayload,
} from '../services/vehicles.service'

export function useVehiclesQuery(params: VehicleListParams) {
  return useQuery({
    queryKey: queryKeys.vehicles.list(params),
    queryFn: () => vehiclesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useVehicleQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.vehicles.detail(id ?? ''),
    queryFn: () => vehiclesService.get(id!),
    enabled: !!id,
  })
}

export function useVehicleHistoryQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.vehicles.history(id ?? ''),
    queryFn: () => vehiclesService.history(id!),
    enabled: !!id,
  })
}

export function useCreateVehicle() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: VehiclePayload) => vehiclesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.vehicles.all })
      toast.success('Veículo criado', 'O veículo foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateVehicle(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: VehiclePayload) => vehiclesService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.vehicles.all })
      toast.success('Veículo atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteVehicle() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => vehiclesService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.vehicles.all })
      toast.success('Veículo removido', 'O veículo foi excluído com sucesso.')
    },
  })
}

function invalidateFleet(queryClient: ReturnType<typeof useQueryClient>, vehicleId: string) {
  queryClient.invalidateQueries({ queryKey: queryKeys.vehicles.all })
  queryClient.invalidateQueries({ queryKey: queryKeys.vehicles.detail(vehicleId) })
  queryClient.invalidateQueries({ queryKey: queryKeys.vehicles.history(vehicleId) })
  queryClient.invalidateQueries({ queryKey: queryKeys.equipments.all })
}

export function useInstallEquipment(vehicleId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: AssignmentPayload) => vehiclesService.install(vehicleId, payload),
    onSuccess: () => {
      invalidateFleet(queryClient, vehicleId)
      toast.success('Equipamento instalado', 'A instalação foi registrada.')
    },
  })
}

export function useRemoveEquipment(vehicleId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: AssignmentPayload = {}) =>
      vehiclesService.removeEquipment(vehicleId, payload),
    onSuccess: () => {
      invalidateFleet(queryClient, vehicleId)
      toast.success('Equipamento removido', 'A remoção foi registrada.')
    },
  })
}

export function useSwapEquipment(vehicleId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: AssignmentPayload) => vehiclesService.swap(vehicleId, payload),
    onSuccess: () => {
      invalidateFleet(queryClient, vehicleId)
      toast.success('Equipamento trocado', 'A troca foi registrada.')
    },
  })
}
