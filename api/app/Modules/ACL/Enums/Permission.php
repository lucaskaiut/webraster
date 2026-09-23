<?php

namespace App\Modules\ACL\Enums;

enum Permission: string
{
    case USER_CREATE = 'user.create';
    case USER_READ = 'user.read';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';

    case TENANT_READ = 'tenant.read';
    case TENANT_UPDATE = 'tenant.update';
    case TENANT_CREATE = 'tenant.create';

    case ROLE_CREATE = 'role.create';
    case ROLE_READ = 'role.read';
    case ROLE_UPDATE = 'role.update';
    case ROLE_DELETE = 'role.delete';

    case API_TOKEN_CREATE = 'api-token.create';
    case API_TOKEN_READ = 'api-token.read';
    case API_TOKEN_DELETE = 'api-token.delete';

    case WEBHOOK_CREATE = 'webhook.create';
    case WEBHOOK_READ = 'webhook.read';
    case WEBHOOK_UPDATE = 'webhook.update';
    case WEBHOOK_DELETE = 'webhook.delete';

    case PLAN_CREATE = 'plan.create';
    case PLAN_READ = 'plan.read';
    case PLAN_UPDATE = 'plan.update';
    case PLAN_DELETE = 'plan.delete';

    case SUBSCRIPTION_READ = 'subscription.read';
    case SUBSCRIPTION_UPDATE = 'subscription.update';

    case INVOICE_READ = 'invoice.read';

    case AUDIT_VIEW = 'audit.view';

    case ASSISTANT_VIEW = 'assistant.view';

    case CLIENT_CREATE = 'client.create';
    case CLIENT_READ = 'client.read';
    case CLIENT_UPDATE = 'client.update';
    case CLIENT_DELETE = 'client.delete';

    case DRIVER_CREATE = 'driver.create';
    case DRIVER_READ = 'driver.read';
    case DRIVER_UPDATE = 'driver.update';
    case DRIVER_DELETE = 'driver.delete';

    case SERVICE_CREATE = 'service.create';
    case SERVICE_READ = 'service.read';
    case SERVICE_UPDATE = 'service.update';
    case SERVICE_DELETE = 'service.delete';

    case CONTRACT_CREATE = 'contract.create';
    case CONTRACT_READ = 'contract.read';
    case CONTRACT_UPDATE = 'contract.update';
    case CONTRACT_DELETE = 'contract.delete';
    case CONTRACT_SIGN = 'contract.sign';

    case VEHICLE_CREATE = 'vehicle.create';
    case VEHICLE_READ = 'vehicle.read';
    case VEHICLE_UPDATE = 'vehicle.update';
    case VEHICLE_DELETE = 'vehicle.delete';

    case VEHICLE_DATA_CONFIG_READ = 'vehicle-data-config.read';
    case VEHICLE_DATA_CONFIG_UPDATE = 'vehicle-data-config.update';

    case EQUIPMENT_CREATE = 'equipment.create';
    case EQUIPMENT_READ = 'equipment.read';
    case EQUIPMENT_UPDATE = 'equipment.update';
    case EQUIPMENT_DELETE = 'equipment.delete';
    case EQUIPMENT_DETAILS_READ = 'equipment.details.read';

    case TRACKING_READ = 'tracking.read';

    case REPORT_VIEW = 'report.view';
    case REPORT_EXPORT = 'report.export';

    case GEOFENCE_CREATE = 'geofence.create';
    case GEOFENCE_READ = 'geofence.read';
    case GEOFENCE_UPDATE = 'geofence.update';
    case GEOFENCE_DELETE = 'geofence.delete';

    case POI_CREATE = 'poi.create';
    case POI_READ = 'poi.read';
    case POI_UPDATE = 'poi.update';
    case POI_DELETE = 'poi.delete';

    case ALERT_READ = 'alert.read';
    case ALERT_MANAGE = 'alert.manage';
    case ALERT_CONFIG_READ = 'alert-config.read';
    case ALERT_CONFIG_UPDATE = 'alert-config.update';
    case NOTIFICATION_READ = 'notification.read';

    case DEVICE_COMMANDS_SEND = 'device.commands.send';

    case SERVICE_ORDER_CREATE = 'service-order.create';
    case SERVICE_ORDER_READ = 'service-order.read';
    case SERVICE_ORDER_UPDATE = 'service-order.update';
    case SERVICE_ORDER_DELETE = 'service-order.delete';
    case SERVICE_ORDER_CHANGE_STATUS = 'service-order.change-status';

    case FINANCE_PLAN_CREATE = 'finance-plan.create';
    case FINANCE_PLAN_READ = 'finance-plan.read';
    case FINANCE_PLAN_UPDATE = 'finance-plan.update';
    case FINANCE_PLAN_DELETE = 'finance-plan.delete';

    case FINANCE_SUBSCRIPTION_CREATE = 'finance-subscription.create';
    case FINANCE_SUBSCRIPTION_READ = 'finance-subscription.read';
    case FINANCE_SUBSCRIPTION_UPDATE = 'finance-subscription.update';

    case FINANCE_BILLING_CREATE = 'finance-billing.create';
    case FINANCE_BILLING_READ = 'finance-billing.read';
    case FINANCE_BILLING_UPDATE = 'finance-billing.update';
    case FINANCE_BILLING_CHARGE = 'finance-billing.charge';

    case FINANCE_GATEWAY_CONFIG_READ = 'finance-gateway-config.read';
    case FINANCE_GATEWAY_CONFIG_UPDATE = 'finance-gateway-config.update';

    case FINANCE_DASHBOARD_READ = 'finance-dashboard.read';
    case FINANCE_REPORT_READ = 'finance-report.read';
    case FINANCE_PORTAL_VIEW = 'finance-portal.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
