import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import {
  clientsService,
  type ClientContractPayload,
  type ClientContractSignaturePayload,
  type ClientOrderPayload,
  type ClientPayload,
  type ClientUserPayload,
} from '../services/clients.service'

export function useClientsQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.clients.list(params),
    queryFn: () => clientsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useClientQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.clients.detail(id ?? ''),
    queryFn: () => clientsService.get(id!),
    enabled: !!id,
  })
}

export function useCreateClient() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ClientPayload) => clientsService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.all })
      toast.success('Cliente criado', 'O cliente foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateClient(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ClientPayload) => clientsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.all })
      toast.success('Cliente atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteClient() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => clientsService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.all })
      toast.success('Cliente removido', 'O cliente foi excluído com sucesso.')
    },
  })
}

export function useSetClientWithoutContract(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (withoutContract: boolean) =>
      clientsService.setWithoutContract(clientId, withoutContract),
    onSuccess: (_client, withoutContract) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.all })
      toast.success(
        withoutContract ? 'Cliente sem contrato' : 'Contrato exigido novamente',
        withoutContract
          ? 'O acesso do cliente no aplicativo já está liberado.'
          : 'O cliente voltará a precisar de um contrato assinado.',
      )
    },
  })
}

export function useClientUsersQuery(clientId: string | undefined, params: ListParams) {
  return useQuery({
    queryKey: queryKeys.clients.users(clientId ?? '', params),
    queryFn: () => clientsService.listUsers(clientId!, params),
    enabled: !!clientId,
    placeholderData: keepPreviousData,
  })
}

export function useCreateClientUser(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ClientUserPayload) => clientsService.createUser(clientId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.all })
      toast.success('Usuário criado', 'O usuário do cliente foi cadastrado.')
    },
  })
}

export function useDeleteClientUser(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (userId: string) => clientsService.removeUser(clientId, userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.all })
      toast.success('Usuário removido', 'O usuário do cliente foi excluído.')
    },
  })
}

export function useClientOrderQuery(clientId: string | undefined) {
  return useQuery({
    queryKey: queryKeys.clients.order(clientId ?? ''),
    queryFn: () => clientsService.getOrder(clientId!),
    enabled: !!clientId,
  })
}

export function useUpsertClientOrder(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ClientOrderPayload) => clientsService.upsertOrder(clientId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.order(clientId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.finance.all })
      toast.success('Pedido salvo', 'A recorrência do cliente foi atualizada.')
    },
  })
}

export function useClientContractQuery(clientId: string | undefined) {
  return useQuery({
    queryKey: queryKeys.clients.contract(clientId ?? ''),
    queryFn: () => clientsService.getContract(clientId!),
    enabled: !!clientId,
  })
}

export function useUpsertClientContract(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ClientContractPayload) => clientsService.upsertContract(clientId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.contract(clientId) })
      toast.success('Contrato salvo', 'O contrato do cliente foi vinculado.')
    },
  })
}

export function useDownloadClientContractPdf(clientId: string) {
  return useMutation({
    mutationFn: () => clientsService.downloadContractPdf(clientId),
  })
}

export function useUpdateClientContractSignature(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ClientContractSignaturePayload) =>
      clientsService.updateContractSignature(clientId, payload),
    onSuccess: (contract) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.contract(clientId) })
      toast.success(
        contract.signature_status === 'signed' ? 'Contrato assinado' : 'Assinatura pendente',
        contract.signature_status === 'signed'
          ? 'O contrato foi marcado como assinado.'
          : 'O contrato foi marcado como pendente de assinatura.',
      )
    },
  })
}

export function useUploadClientContractDocument(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (file: File) => clientsService.uploadContractDocument(clientId, file),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.contract(clientId) })
      toast.success('Contrato importado', 'O contrato foi enviado e marcado como assinado.')
    },
  })
}

export function useClientSharedVehiclesQuery(clientId: string | undefined) {
  return useQuery({
    queryKey: queryKeys.clients.sharedVehicles(clientId ?? ''),
    queryFn: () => clientsService.listSharedVehicles(clientId as string, { per_page: 100 }),
    enabled: Boolean(clientId),
  })
}

export function useShareClientVehicle(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (vehicleId: string) => clientsService.shareVehicle(clientId, vehicleId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.sharedVehicles(clientId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.vehicles.all })
      toast.success('Veículo compartilhado', 'O veículo está disponível para este cliente.')
    },
  })
}

export function useRemoveClientVehicleShare(clientId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (shareId: string) => clientsService.removeVehicleShare(clientId, shareId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.clients.sharedVehicles(clientId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.vehicles.all })
      toast.success('Compartilhamento removido')
    },
  })
}
