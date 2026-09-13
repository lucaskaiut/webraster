import { useRef, useState } from 'react'
import { isApiError } from '@/shared/api/errors'
import type { VehicleData } from '@/shared/types/models'
import { vehicleDataService } from '../services/vehicle-data.service'

export type PlateLookupStatus = 'idle' | 'loading' | 'ok' | 'error'

/**
 * Consulta de dados veiculares por placa (disparada no blur do campo),
 * no estilo do autopreenchimento de endereço por CEP.
 */
export function usePlateLookup(onFilled: (data: VehicleData) => void) {
  const [status, setStatus] = useState<PlateLookupStatus>('idle')
  const [hint, setHint] = useState<string | undefined>()
  const lastPlate = useRef<string | null>(null)

  const lookup = async (rawPlate: string) => {
    const plate = rawPlate.toUpperCase().replace(/[^A-Z0-9]/g, '')

    if (plate.length !== 7) {
      if (plate === '') {
        lastPlate.current = null
        setStatus('idle')
        setHint(undefined)
      }
      return
    }

    if (lastPlate.current === plate) return
    lastPlate.current = plate

    setStatus('loading')
    setHint('Consultando placa...')

    try {
      const data = await vehicleDataService.lookup(plate)
      onFilled(data)
      setStatus('ok')
      setHint('Dados preenchidos pela placa.')
    } catch (error) {
      setStatus('error')
      setHint(isApiError(error) ? error.message : 'Não foi possível consultar a placa.')
    }
  }

  return {
    status,
    hint,
    loading: status === 'loading',
    lookup,
  }
}
