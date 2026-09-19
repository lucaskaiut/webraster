<?php

namespace App\Modules\ACL\Enums;

enum DefaultRole: string
{
    case ADMINISTRATOR = 'Administrador';
    case OPERATOR = 'Operador';
    case CLIENT = 'Cliente';

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Acesso completo ao tenant.',
            self::OPERATOR => 'Acesso operacional para gestão do dia a dia.',
            self::CLIENT => 'Acesso limitado do cliente final.',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMINISTRATOR => Permission::cases(),
            self::OPERATOR => [
                Permission::USER_READ,
                Permission::TENANT_READ,
                Permission::ROLE_READ,
                Permission::API_TOKEN_READ,
                Permission::WEBHOOK_READ,
                Permission::SUBSCRIPTION_READ,
                Permission::INVOICE_READ,
                Permission::AUDIT_VIEW,
                Permission::ASSISTANT_VIEW,
                Permission::CLIENT_CREATE,
                Permission::CLIENT_READ,
                Permission::CLIENT_UPDATE,
                Permission::CLIENT_DELETE,
                Permission::DRIVER_CREATE,
                Permission::DRIVER_READ,
                Permission::DRIVER_UPDATE,
                Permission::DRIVER_DELETE,
                Permission::SERVICE_CREATE,
                Permission::SERVICE_READ,
                Permission::SERVICE_UPDATE,
                Permission::SERVICE_DELETE,
                Permission::CONTRACT_CREATE,
                Permission::CONTRACT_READ,
                Permission::CONTRACT_UPDATE,
                Permission::CONTRACT_DELETE,
                Permission::VEHICLE_CREATE,
                Permission::VEHICLE_READ,
                Permission::VEHICLE_UPDATE,
                Permission::VEHICLE_DELETE,
                Permission::EQUIPMENT_CREATE,
                Permission::EQUIPMENT_READ,
                Permission::EQUIPMENT_UPDATE,
                Permission::EQUIPMENT_DELETE,
                Permission::TRACKING_READ,
                Permission::GEOFENCE_CREATE,
                Permission::GEOFENCE_READ,
                Permission::GEOFENCE_UPDATE,
                Permission::GEOFENCE_DELETE,
                Permission::POI_CREATE,
                Permission::POI_READ,
                Permission::POI_UPDATE,
                Permission::POI_DELETE,
                Permission::ALERT_READ,
                Permission::ALERT_MANAGE,
                Permission::ALERT_CONFIG_READ,
                Permission::ALERT_CONFIG_UPDATE,
                Permission::NOTIFICATION_READ,
                Permission::DEVICE_COMMANDS_SEND,
                Permission::SERVICE_ORDER_CREATE,
                Permission::SERVICE_ORDER_READ,
                Permission::SERVICE_ORDER_UPDATE,
                Permission::SERVICE_ORDER_DELETE,
                Permission::SERVICE_ORDER_CHANGE_STATUS,
                Permission::FINANCE_PLAN_CREATE,
                Permission::FINANCE_PLAN_READ,
                Permission::FINANCE_PLAN_UPDATE,
                Permission::FINANCE_PLAN_DELETE,
                Permission::FINANCE_SUBSCRIPTION_CREATE,
                Permission::FINANCE_SUBSCRIPTION_READ,
                Permission::FINANCE_SUBSCRIPTION_UPDATE,
                Permission::FINANCE_BILLING_CREATE,
                Permission::FINANCE_BILLING_READ,
                Permission::FINANCE_BILLING_UPDATE,
                Permission::FINANCE_BILLING_CHARGE,
                Permission::FINANCE_GATEWAY_CONFIG_READ,
                Permission::FINANCE_GATEWAY_CONFIG_UPDATE,
                Permission::FINANCE_DASHBOARD_READ,
                Permission::FINANCE_REPORT_READ,
            ],
            self::CLIENT => [
                Permission::SUBSCRIPTION_READ,
                Permission::INVOICE_READ,
                Permission::ASSISTANT_VIEW,
                Permission::CONTRACT_SIGN,
                Permission::VEHICLE_READ,
                Permission::TRACKING_READ,
                Permission::GEOFENCE_READ,
                Permission::POI_READ,
                Permission::ALERT_READ,
                Permission::NOTIFICATION_READ,
                Permission::SERVICE_ORDER_READ,
                Permission::FINANCE_PORTAL_VIEW,
            ],
        };
    }
}
