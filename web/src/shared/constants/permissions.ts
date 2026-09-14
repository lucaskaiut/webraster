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

  SERVICE_CREATE: 'service.create',
  SERVICE_READ: 'service.read',
  SERVICE_UPDATE: 'service.update',
  SERVICE_DELETE: 'service.delete',

  CONTRACT_CREATE: 'contract.create',
  CONTRACT_READ: 'contract.read',
  CONTRACT_UPDATE: 'contract.update',
  CONTRACT_DELETE: 'contract.delete',

  VEHICLE_CREATE: 'vehicle.create',
  VEHICLE_READ: 'vehicle.read',
  VEHICLE_UPDATE: 'vehicle.update',
  VEHICLE_DELETE: 'vehicle.delete',

  VEHICLE_DATA_CONFIG_READ: 'vehicle-data-config.read',
  VEHICLE_DATA_CONFIG_UPDATE: 'vehicle-data-config.update',

  EQUIPMENT_CREATE: 'equipment.create',
  EQUIPMENT_READ: 'equipment.read',
  EQUIPMENT_UPDATE: 'equipment.update',
  EQUIPMENT_DELETE: 'equipment.delete',

  TRACKING_READ: 'tracking.read',

  REPORT_VIEW: 'report.view',
  REPORT_EXPORT: 'report.export',

  DEVICE_COMMANDS_SEND: 'device.commands.send',

  SERVICE_ORDER_CREATE: 'service-order.create',
  SERVICE_ORDER_READ: 'service-order.read',
  SERVICE_ORDER_UPDATE: 'service-order.update',
  SERVICE_ORDER_DELETE: 'service-order.delete',
  SERVICE_ORDER_CHANGE_STATUS: 'service-order.change-status',

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

  FINANCE_PLAN_CREATE: 'finance-plan.create',
  FINANCE_PLAN_READ: 'finance-plan.read',
  FINANCE_PLAN_UPDATE: 'finance-plan.update',
  FINANCE_PLAN_DELETE: 'finance-plan.delete',

  FINANCE_SUBSCRIPTION_CREATE: 'finance-subscription.create',
  FINANCE_SUBSCRIPTION_READ: 'finance-subscription.read',
  FINANCE_SUBSCRIPTION_UPDATE: 'finance-subscription.update',

  FINANCE_BILLING_CREATE: 'finance-billing.create',
  FINANCE_BILLING_READ: 'finance-billing.read',
  FINANCE_BILLING_UPDATE: 'finance-billing.update',
  FINANCE_BILLING_CHARGE: 'finance-billing.charge',

  FINANCE_GATEWAY_CONFIG_READ: 'finance-gateway-config.read',
  FINANCE_GATEWAY_CONFIG_UPDATE: 'finance-gateway-config.update',

  FINANCE_DASHBOARD_READ: 'finance-dashboard.read',
  FINANCE_REPORT_READ: 'finance-report.read',
  FINANCE_PORTAL_VIEW: 'finance-portal.view',
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
    label: 'Serviços',
    permissions: [
      { value: Permission.SERVICE_READ, label: 'Visualizar serviços' },
      { value: Permission.SERVICE_CREATE, label: 'Criar serviços' },
      { value: Permission.SERVICE_UPDATE, label: 'Editar serviços' },
      { value: Permission.SERVICE_DELETE, label: 'Remover serviços' },
    ],
  },
  {
    label: 'Contratos',
    permissions: [
      { value: Permission.CONTRACT_READ, label: 'Visualizar contratos' },
      { value: Permission.CONTRACT_CREATE, label: 'Criar contratos' },
      { value: Permission.CONTRACT_UPDATE, label: 'Editar contratos' },
      { value: Permission.CONTRACT_DELETE, label: 'Remover contratos' },
    ],
  },
  {
    label: 'Veículos',
    permissions: [
      { value: Permission.VEHICLE_READ, label: 'Visualizar veículos' },
      { value: Permission.VEHICLE_CREATE, label: 'Criar veículos' },
      { value: Permission.VEHICLE_UPDATE, label: 'Editar veículos' },
      { value: Permission.VEHICLE_DELETE, label: 'Remover veículos' },
      {
        value: Permission.VEHICLE_DATA_CONFIG_READ,
        label: 'Visualizar configuração da consulta de placa',
      },
      {
        value: Permission.VEHICLE_DATA_CONFIG_UPDATE,
        label: 'Editar configuração da consulta de placa',
      },
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
    permissions: [
      { value: Permission.TRACKING_READ, label: 'Visualizar mapa e histórico' },
      { value: Permission.DEVICE_COMMANDS_SEND, label: 'Enviar comandos ao dispositivo' },
    ],
  },
  {
    label: 'Relatórios',
    permissions: [
      { value: Permission.REPORT_VIEW, label: 'Visualizar relatórios' },
      { value: Permission.REPORT_EXPORT, label: 'Exportar relatórios' },
    ],
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
    label: 'Ordens de serviço',
    permissions: [
      { value: Permission.SERVICE_ORDER_READ, label: 'Visualizar ordens de serviço' },
      { value: Permission.SERVICE_ORDER_CREATE, label: 'Criar ordens de serviço' },
      { value: Permission.SERVICE_ORDER_UPDATE, label: 'Editar ordens de serviço' },
      { value: Permission.SERVICE_ORDER_DELETE, label: 'Remover ordens de serviço' },
      { value: Permission.SERVICE_ORDER_CHANGE_STATUS, label: 'Alterar status de OS' },
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
  {
    label: 'Financeiro',
    permissions: [
      { value: Permission.FINANCE_PLAN_READ, label: 'Visualizar planos financeiros' },
      { value: Permission.FINANCE_PLAN_CREATE, label: 'Criar planos financeiros' },
      { value: Permission.FINANCE_PLAN_UPDATE, label: 'Editar planos financeiros' },
      { value: Permission.FINANCE_PLAN_DELETE, label: 'Remover planos financeiros' },
      { value: Permission.FINANCE_SUBSCRIPTION_READ, label: 'Visualizar assinaturas financeiras' },
      { value: Permission.FINANCE_SUBSCRIPTION_CREATE, label: 'Criar assinaturas financeiras' },
      { value: Permission.FINANCE_SUBSCRIPTION_UPDATE, label: 'Gerenciar assinaturas financeiras' },
      { value: Permission.FINANCE_BILLING_READ, label: 'Visualizar cobranças' },
      { value: Permission.FINANCE_BILLING_CREATE, label: 'Gerar cobranças' },
      { value: Permission.FINANCE_BILLING_UPDATE, label: 'Atualizar cobranças' },
      { value: Permission.FINANCE_BILLING_CHARGE, label: 'Cobrar via gateway' },
      { value: Permission.FINANCE_GATEWAY_CONFIG_READ, label: 'Visualizar configuração do gateway' },
      { value: Permission.FINANCE_GATEWAY_CONFIG_UPDATE, label: 'Editar configuração do gateway' },
      { value: Permission.FINANCE_DASHBOARD_READ, label: 'Visualizar dashboard financeiro' },
      { value: Permission.FINANCE_REPORT_READ, label: 'Visualizar relatórios financeiros' },
      { value: Permission.FINANCE_PORTAL_VIEW, label: 'Acessar portal financeiro do cliente' },
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
