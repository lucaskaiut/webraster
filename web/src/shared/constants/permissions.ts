export const Permission = {
  USER_CREATE: 'user.create',
  USER_READ: 'user.read',
  USER_UPDATE: 'user.update',
  USER_DELETE: 'user.delete',

  TENANT_READ: 'tenant.read',
  TENANT_UPDATE: 'tenant.update',
  TENANT_CREATE: 'tenant.create',

  ROLE_CREATE: 'role.create',
  ROLE_READ: 'role.read',
  ROLE_UPDATE: 'role.update',
  ROLE_DELETE: 'role.delete',

  API_TOKEN_CREATE: 'api-token.create',
  API_TOKEN_READ: 'api-token.read',
  API_TOKEN_DELETE: 'api-token.delete',

  WEBHOOK_CREATE: 'webhook.create',
  WEBHOOK_READ: 'webhook.read',
  WEBHOOK_UPDATE: 'webhook.update',
  WEBHOOK_DELETE: 'webhook.delete',

  PLAN_CREATE: 'plan.create',
  PLAN_READ: 'plan.read',
  PLAN_UPDATE: 'plan.update',
  PLAN_DELETE: 'plan.delete',

  SUBSCRIPTION_READ: 'subscription.read',
  SUBSCRIPTION_UPDATE: 'subscription.update',

  INVOICE_READ: 'invoice.read',

  AUDIT_VIEW: 'audit.view',

  ASSISTANT_VIEW: 'assistant.view',

  CLIENT_CREATE: 'client.create',
  CLIENT_READ: 'client.read',
  CLIENT_UPDATE: 'client.update',
  CLIENT_DELETE: 'client.delete',

  DRIVER_CREATE: 'driver.create',
  DRIVER_READ: 'driver.read',
  DRIVER_UPDATE: 'driver.update',
  DRIVER_DELETE: 'driver.delete',

  VEHICLE_CREATE: 'vehicle.create',
  VEHICLE_READ: 'vehicle.read',
  VEHICLE_UPDATE: 'vehicle.update',
  VEHICLE_DELETE: 'vehicle.delete',

  EQUIPMENT_CREATE: 'equipment.create',
  EQUIPMENT_READ: 'equipment.read',
  EQUIPMENT_UPDATE: 'equipment.update',
  EQUIPMENT_DELETE: 'equipment.delete',

  TRACKING_READ: 'tracking.read',

  GEOFENCE_CREATE: 'geofence.create',
  GEOFENCE_READ: 'geofence.read',
  GEOFENCE_UPDATE: 'geofence.update',
  GEOFENCE_DELETE: 'geofence.delete',

  POI_CREATE: 'poi.create',
  POI_READ: 'poi.read',
  POI_UPDATE: 'poi.update',
  POI_DELETE: 'poi.delete',

  ALERT_READ: 'alert.read',
  ALERT_MANAGE: 'alert.manage',
  ALERT_CONFIG_READ: 'alert-config.read',
  ALERT_CONFIG_UPDATE: 'alert-config.update',
  NOTIFICATION_READ: 'notification.read',
} as const

export type Permission = (typeof Permission)[keyof typeof Permission]

export interface PermissionGroup {
  label: string
  permissions: Array<{ value: Permission; label: string }>
}

export const PERMISSION_GROUPS: PermissionGroup[] = [
  {
    label: 'Usuários',
    permissions: [
      { value: Permission.USER_READ, label: 'Visualizar usuários' },
      { value: Permission.USER_CREATE, label: 'Criar usuários' },
      { value: Permission.USER_UPDATE, label: 'Editar usuários' },
      { value: Permission.USER_DELETE, label: 'Remover usuários' },
    ],
  },
  {
    label: 'Organização',
    permissions: [
      { value: Permission.TENANT_READ, label: 'Visualizar dados da organização' },
      { value: Permission.TENANT_UPDATE, label: 'Editar dados da organização' },
      { value: Permission.TENANT_CREATE, label: 'Criar empresas' },
    ],
  },
  {
    label: 'Perfis de acesso',
    permissions: [
      { value: Permission.ROLE_READ, label: 'Visualizar perfis' },
      { value: Permission.ROLE_CREATE, label: 'Criar perfis' },
      { value: Permission.ROLE_UPDATE, label: 'Editar perfis' },
      { value: Permission.ROLE_DELETE, label: 'Remover perfis' },
    ],
  },
  // Tokens de API e Webhooks ocultos no frontend
  // {
  //   label: 'Tokens de API',
  //   permissions: [
  //     { value: Permission.API_TOKEN_READ, label: 'Visualizar tokens' },
  //     { value: Permission.API_TOKEN_CREATE, label: 'Criar tokens' },
  //     { value: Permission.API_TOKEN_DELETE, label: 'Revogar tokens' },
  //   ],
  // },
  // {
  //   label: 'Webhooks',
  //   permissions: [
  //     { value: Permission.WEBHOOK_READ, label: 'Visualizar webhooks' },
  //     { value: Permission.WEBHOOK_CREATE, label: 'Criar webhooks' },
  //     { value: Permission.WEBHOOK_UPDATE, label: 'Editar webhooks' },
  //     { value: Permission.WEBHOOK_DELETE, label: 'Remover webhooks' },
  //   ],
  // },
  {
    label: 'Auditoria',
    permissions: [{ value: Permission.AUDIT_VIEW, label: 'Visualizar auditoria' }],
  },
  // Assistente de IA oculto no frontend
  // {
  //   label: 'Assistente de IA',
  //   permissions: [{ value: Permission.ASSISTANT_VIEW, label: 'Usar o assistente de IA' }],
  // },
  // Controle de assinatura desabilitado neste sistema
  // {
  //   label: 'Assinaturas',
  //   permissions: [
  //     { value: Permission.PLAN_READ, label: 'Visualizar planos' },
  //     { value: Permission.PLAN_CREATE, label: 'Criar planos' },
  //     { value: Permission.PLAN_UPDATE, label: 'Editar planos' },
  //     { value: Permission.PLAN_DELETE, label: 'Inativar planos' },
  //     { value: Permission.SUBSCRIPTION_READ, label: 'Visualizar assinatura' },
  //     { value: Permission.SUBSCRIPTION_UPDATE, label: 'Gerenciar assinatura' },
  //     { value: Permission.INVOICE_READ, label: 'Visualizar cobranças' },
  //   ],
  // },
  {
    label: 'Clientes',
    permissions: [
      { value: Permission.CLIENT_READ, label: 'Visualizar clientes' },
      { value: Permission.CLIENT_CREATE, label: 'Criar clientes' },
      { value: Permission.CLIENT_UPDATE, label: 'Editar clientes' },
      { value: Permission.CLIENT_DELETE, label: 'Remover clientes' },
    ],
  },
  {
    label: 'Motoristas',
    permissions: [
      { value: Permission.DRIVER_READ, label: 'Visualizar motoristas' },
      { value: Permission.DRIVER_CREATE, label: 'Criar motoristas' },
      { value: Permission.DRIVER_UPDATE, label: 'Editar motoristas' },
      { value: Permission.DRIVER_DELETE, label: 'Remover motoristas' },
    ],
  },
  {
    label: 'Veículos',
    permissions: [
      { value: Permission.VEHICLE_READ, label: 'Visualizar veículos' },
      { value: Permission.VEHICLE_CREATE, label: 'Criar veículos' },
      { value: Permission.VEHICLE_UPDATE, label: 'Editar veículos' },
      { value: Permission.VEHICLE_DELETE, label: 'Remover veículos' },
    ],
  },
  {
    label: 'Equipamentos',
    permissions: [
      { value: Permission.EQUIPMENT_READ, label: 'Visualizar equipamentos' },
      { value: Permission.EQUIPMENT_CREATE, label: 'Criar equipamentos' },
      { value: Permission.EQUIPMENT_UPDATE, label: 'Editar equipamentos' },
      { value: Permission.EQUIPMENT_DELETE, label: 'Remover equipamentos' },
    ],
  },
  {
    label: 'Monitoramento',
    permissions: [{ value: Permission.TRACKING_READ, label: 'Visualizar mapa e histórico' }],
  },
  {
    label: 'Geocercas',
    permissions: [
      { value: Permission.GEOFENCE_READ, label: 'Visualizar geocercas' },
      { value: Permission.GEOFENCE_CREATE, label: 'Criar geocercas' },
      { value: Permission.GEOFENCE_UPDATE, label: 'Editar geocercas' },
      { value: Permission.GEOFENCE_DELETE, label: 'Remover geocercas' },
    ],
  },
  {
    label: 'POIs',
    permissions: [
      { value: Permission.POI_READ, label: 'Visualizar POIs' },
      { value: Permission.POI_CREATE, label: 'Criar POIs' },
      { value: Permission.POI_UPDATE, label: 'Editar POIs' },
      { value: Permission.POI_DELETE, label: 'Remover POIs' },
    ],
  },
  {
    label: 'Alertas',
    permissions: [
      { value: Permission.ALERT_READ, label: 'Visualizar alertas' },
      { value: Permission.ALERT_MANAGE, label: 'Gerenciar alertas' },
      { value: Permission.ALERT_CONFIG_READ, label: 'Visualizar configuração de alertas' },
      { value: Permission.ALERT_CONFIG_UPDATE, label: 'Editar configuração de alertas' },
      { value: Permission.NOTIFICATION_READ, label: 'Visualizar notificações' },
    ],
  },
]

/** Permissões de cadastro de planos — só fazem sentido em tenants umbrella. */
export const PLAN_PERMISSIONS: Permission[] = [
  Permission.PLAN_CREATE,
  Permission.PLAN_READ,
  Permission.PLAN_UPDATE,
  Permission.PLAN_DELETE,
]

export function isPlanPermission(permission: Permission | string): boolean {
  return String(permission).startsWith('plan.')
}

export function getPermissionGroups(options?: { includePlanPermissions?: boolean }): PermissionGroup[] {
  const includePlanPermissions = options?.includePlanPermissions ?? true

  if (includePlanPermissions) {
    return PERMISSION_GROUPS
  }

  return PERMISSION_GROUPS.map((group) => ({
    ...group,
    permissions: group.permissions.filter((item) => !isPlanPermission(item.value)),
  })).filter((group) => group.permissions.length > 0)
}
