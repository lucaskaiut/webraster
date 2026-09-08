/** Tradução visual apenas — sem lógica de negócio. */
export const commandLabels: Record<string, string> = {
  engineStop: 'Bloquear Motor',
  engineResume: 'Desbloquear Motor',
  alarmArm: 'Armar Alarme',
  alarmDisarm: 'Desarmar Alarme',
  custom: 'Comando Personalizado',
  deviceIdentification: 'Identificação do dispositivo',
  positionSingle: 'Solicitar posição',
  positionPeriodic: 'Posição periódica',
  positionStop: 'Parar posições',
  requestPhoto: 'Solicitar foto',
  powerOff: 'Desligar dispositivo',
  rebootDevice: 'Reiniciar dispositivo',
  factoryReset: 'Restaurar fábrica',
  setTimezone: 'Definir fuso horário',
  sosNumber: 'Número SOS',
  silenceTime: 'Horário silencioso',
  setPhonebook: 'Agenda telefônica',
  voiceMessage: 'Mensagem de voz',
  outputControl: 'Controle de saída',
  sendSms: 'Enviar SMS',
}

/** Comandos que exigem confirmação antes do envio. */
export const criticalCommands = new Set([
  'engineStop',
  'engineResume',
  'alarmArm',
  'alarmDisarm',
  'powerOff',
  'rebootDevice',
  'factoryReset',
])

export function commandLabel(type: string): string {
  return commandLabels[type] ?? type
}

export function isCriticalCommand(type: string): boolean {
  return criticalCommands.has(type)
}
