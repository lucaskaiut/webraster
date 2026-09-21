import type { LucideIcon } from 'lucide-react'
import {
  Bike,
  Briefcase,
  Bus,
  Car,
  CarFront,
  Caravan,
  CircleHelp,
  Construction,
  Container,
  Factory,
  Forklift,
  Motorbike,
  PawPrint,
  PersonStanding,
  Ship,
  Smartphone,
  Speaker,
  Tractor,
  Truck,
  Van,
  Zap,
} from 'lucide-react'

export interface VehicleTypeOption {
  value: number
  label: string
  icon: LucideIcon
}

export const DEFAULT_VEHICLE_TYPE = 1

export const VEHICLE_TYPES: VehicleTypeOption[] = [
  { value: 4, label: 'Veículo Aquático', icon: Ship },
  { value: 9, label: 'Celular', icon: Smartphone },
  { value: 10, label: 'BAG', icon: Briefcase },
  { value: 13, label: 'Bicicleta', icon: Bike },
  { value: 14, label: 'Paredão', icon: Speaker },
  { value: 998, label: 'Animal', icon: PawPrint },
  { value: 999, label: 'Pessoa', icon: PersonStanding },
  { value: 1, label: 'Carro', icon: Car },
  { value: 16, label: 'Buggy', icon: CarFront },
  { value: 2, label: 'Moto', icon: Motorbike },
  { value: 17, label: 'Moto Elétrica', icon: Motorbike },
  { value: 8, label: 'Pickup', icon: Truck },
  { value: 5, label: 'Caminhão', icon: Truck },
  { value: 7, label: 'Ônibus', icon: Bus },
  { value: 3, label: 'VAN', icon: Van },
  { value: 6, label: 'Trator', icon: Tractor },
  { value: 12, label: 'Retroescavadeira', icon: Construction },
  { value: 15, label: 'Reboque', icon: Caravan },
  { value: 997, label: 'Empilhadeira', icon: Forklift },
  { value: 995, label: 'Máquina de Fusão', icon: Factory },
  { value: 1000, label: 'Carreta', icon: Truck },
  { value: 1001, label: 'Triciclo', icon: Bike },
  { value: 11, label: 'Quadriciclo', icon: CarFront },
  { value: 1002, label: 'Caçamba', icon: Container },
  { value: 1003, label: 'Outros', icon: CircleHelp },
  { value: 1004, label: 'Guincho', icon: Truck },
  { value: 1005, label: 'Gerador', icon: Zap },
]

const VEHICLE_TYPE_BY_VALUE = new Map(VEHICLE_TYPES.map((option) => [option.value, option]))

export const vehicleTypeOptions = VEHICLE_TYPES.map((option) => ({
  value: String(option.value),
  label: option.label,
}))

export function vehicleTypeIcon(value: number | null | undefined): LucideIcon {
  if (value === null || value === undefined) {
    return Car
  }

  return VEHICLE_TYPE_BY_VALUE.get(value)?.icon ?? Car
}

export function vehicleTypeLabel(value: number | null | undefined): string | null {
  if (value === null || value === undefined) {
    return null
  }

  return VEHICLE_TYPE_BY_VALUE.get(value)?.label ?? null
}
