import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import type { VehicleData, VehicleTransmission } from '@/shared/types/models'

/**
 * Mapeia o texto de câmbio do provedor para o enum de transmissão do sistema.
 */
export function mapTransmission(value: string | null | undefined): VehicleTransmission | '' {
  if (!value) return ''

  const normalized = value.toLowerCase()

  if (
    normalized.includes('automatiz') ||
    normalized.includes('automated') ||
    normalized.includes('dct') ||
    normalized.includes('dsg') ||
    normalized.includes('cvt') ||
    normalized.includes('dualogic') ||
    normalized.includes('imotion') ||
    normalized.includes('easyr')
  ) {
    return 'automated'
  }

  if (normalized.includes('automatic') || normalized.includes('automático') || normalized.includes('auto')) {
    return 'automatic'
  }

  if (normalized.includes('man') || normalized.includes('mecan')) return 'manual'

  return ''
}

/**
 * Preenche os campos do veículo a partir do resultado da consulta por placa.
 * Só sobrescreve campos ainda vazios (não clobbera digitação manual).
 */
export function fillVehicleFromLookup<T extends FieldValues>(
  form: UseFormReturn<T>,
  data: VehicleData,
): void {
  const setIfEmpty = (name: string, value: string): void => {
    if (value === '') return

    const current = form.getValues(name as Path<T>)
    if (current !== '' && current !== null && current !== undefined) return

    form.setValue(name as Path<T>, value as T[Path<T>], { shouldDirty: true })
  }

  if (data.brand) setIfEmpty('brand', data.brand)
  if (data.model) setIfEmpty('model', data.model)
  if (data.color) setIfEmpty('color', data.color)
  if (data.year) setIfEmpty('year', String(data.year))
  if (data.chassis && !data.chassis.includes('*')) setIfEmpty('chassis', data.chassis)

  const transmission = mapTransmission(data.transmission)
  if (transmission) setIfEmpty('transmission', transmission)

  if (data.fipe) {
    if (data.fipe.code) setIfEmpty('fipe_code', data.fipe.code)
    if (data.fipe.model_year) setIfEmpty('fipe_model_year', data.fipe.model_year)
    if (data.fipe.fuel) setIfEmpty('fipe_fuel', data.fipe.fuel)
    if (data.fipe.reference_month) setIfEmpty('fipe_reference_month', data.fipe.reference_month)
    if (data.fipe.value) setIfEmpty('fipe_value', data.fipe.value)
    if (data.fipe.model) setIfEmpty('fipe_model', data.fipe.model)
    if (data.fipe.brand) setIfEmpty('fipe_brand', data.fipe.brand)
    if (data.fipe.score !== null && data.fipe.score !== undefined) {
      setIfEmpty('fipe_score', String(data.fipe.score))
    }
  }
}
