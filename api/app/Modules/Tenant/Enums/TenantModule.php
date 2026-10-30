<?php

namespace App\Modules\Tenant\Enums;

use App\Modules\ACL\Enums\Permission;

/**
 * Módulos contratáveis por um tenant filho. O umbrella define quais ficam
 * ativos; permissões de módulos desativados deixam de valer para todos os
 * usuários do tenant, mesmo que os perfis as concedam.
 *
 * Permissões fora de qualquer módulo (usuários, perfis, clientes,
 * configurações, auditoria, notificações) são do núcleo e sempre valem.
 * Uma permissão pode pertencer a mais de um módulo (ex.: cadastro de
 * veículos): basta um deles estar ativo.
 */
enum TenantModule: string
{
    case TRACKING = 'tracking';
    case GEOFENCES = 'geofences';
    case ALERTS = 'alerts';
    case REPORTS = 'reports';
    case SERVICE_ORDERS = 'service_orders';
    case CONTRACTS = 'contracts';
    case FINANCE = 'finance';
    case CRM = 'crm';
    case ASSISTANT = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::TRACKING => 'Rastreamento e monitoramento',
            self::GEOFENCES => 'Geocercas e POIs',
            self::ALERTS => 'Alertas e notificações',
            self::REPORTS => 'Relatórios de rastreamento',
            self::SERVICE_ORDERS => 'Ordens de serviço',
            self::CONTRACTS => 'Contratos e assinatura digital',
            self::FINANCE => 'Financeiro',
            self::CRM => 'CRM',
            self::ASSISTANT => 'Assistente de IA',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TRACKING => 'Mapa em tempo real, histórico de percurso, veículos, motoristas, equipamentos, estoques, comandos e logs de dispositivo.',
            self::GEOFENCES => 'Cadastro de geocercas e pontos de interesse, com eventos de entrada e saída.',
            self::ALERTS => 'Painel e lista de alertas, configurações por veículo e cliente, preferências do portal e envio de notificações.',
            self::REPORTS => 'Relatórios de posições, paradas, viagens, eventos e comandos, com exportação.',
            self::SERVICE_ORDERS => 'OS (lista, kanban e calendário), cadastro de serviços, checklists, técnicos, comissões, app do técnico e painel de agendamento.',
            self::CONTRACTS => 'Modelos de contrato, contrato do cliente, assinatura digital e evidências.',
            self::FINANCE => 'Planos, pedidos, assinaturas e cobranças dos clientes, gateway de pagamento, relatórios e portal de faturas.',
            self::CRM => 'Conversas (WhatsApp), leads, kanban, etapas do funil e atendimento por IA.',
            self::ASSISTANT => 'Assistente conversacional de IA.',
        };
    }

    /**
     * Módulos que precisam estar ativos para este funcionar.
     *
     * @return list<self>
     */
    public function requires(): array
    {
        return match ($this) {
            self::GEOFENCES, self::ALERTS, self::REPORTS => [self::TRACKING],
            default => [],
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::TRACKING => [
                Permission::TRACKING_READ,
                Permission::DRIVER_CREATE,
                Permission::DRIVER_READ,
                Permission::DRIVER_UPDATE,
                Permission::DRIVER_DELETE,
                Permission::DEVICE_COMMANDS_SEND,
                ...self::vehicleRegistry(),
                ...self::equipmentRegistry(),
            ],
            self::GEOFENCES => [
                Permission::GEOFENCE_CREATE,
                Permission::GEOFENCE_READ,
                Permission::GEOFENCE_UPDATE,
                Permission::GEOFENCE_DELETE,
                Permission::POI_CREATE,
                Permission::POI_READ,
                Permission::POI_UPDATE,
                Permission::POI_DELETE,
            ],
            self::ALERTS => [
                Permission::ALERT_READ,
                Permission::ALERT_MANAGE,
                Permission::ALERT_CONFIG_READ,
                Permission::ALERT_CONFIG_UPDATE,
                Permission::NOTIFICATION_SEND,
            ],
            self::REPORTS => [
                Permission::REPORT_VIEW,
                Permission::REPORT_EXPORT,
            ],
            self::SERVICE_ORDERS => [
                Permission::SERVICE_ORDER_CREATE,
                Permission::SERVICE_ORDER_READ,
                Permission::SERVICE_ORDER_UPDATE,
                Permission::SERVICE_ORDER_DELETE,
                Permission::SERVICE_ORDER_CHANGE_STATUS,
                Permission::SERVICE_ORDER_CANCEL,
                Permission::SERVICE_ORDER_VIEW_ALL,
                Permission::SERVICE_ORDER_EXECUTE,
                Permission::CHECKLIST_CREATE,
                Permission::CHECKLIST_READ,
                Permission::CHECKLIST_UPDATE,
                Permission::CHECKLIST_DELETE,
                Permission::COMMISSION_READ,
                Permission::COMMISSION_VIEW_OTHERS,
                Permission::TECHNICIAN_READ,
                Permission::TECHNICIAN_MANAGE,
                Permission::PANEL_SCHEDULING_ACCESS,
                ...self::serviceCatalog(),
                ...self::vehicleRegistry(),
                ...self::equipmentRegistry(),
            ],
            self::CONTRACTS => [
                Permission::CONTRACT_CREATE,
                Permission::CONTRACT_READ,
                Permission::CONTRACT_UPDATE,
                Permission::CONTRACT_DELETE,
                Permission::CONTRACT_SIGN,
            ],
            self::FINANCE => [
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
                Permission::FINANCE_BILLING_DELETE,
                Permission::FINANCE_BILLING_CHARGE,
                Permission::FINANCE_GATEWAY_CONFIG_READ,
                Permission::FINANCE_GATEWAY_CONFIG_UPDATE,
                Permission::FINANCE_DASHBOARD_READ,
                Permission::FINANCE_REPORT_READ,
                Permission::FINANCE_PORTAL_VIEW,
                // O pedido do cliente é montado com serviços × veículos.
                ...self::serviceCatalog(),
                ...self::vehicleRegistry(),
            ],
            self::CRM => [
                Permission::CRM_PIPELINE_VIEW,
                Permission::CRM_PIPELINE_MANAGE,
                Permission::CRM_LEAD_VIEW,
                Permission::CRM_LEAD_UPDATE,
                Permission::CRM_CONVERSATION_VIEW,
                Permission::CRM_CONVERSATION_REPLY,
                Permission::CRM_GATEWAY_MANAGE,
                Permission::CRM_AI_VIEW,
                Permission::CRM_AI_MANAGE,
            ],
            self::ASSISTANT => [
                Permission::ASSISTANT_VIEW,
            ],
        };
    }

    /**
     * Módulos que liberam a permissão. Vazio = permissão do núcleo.
     *
     * @return list<self>
     */
    public static function providing(Permission|string $permission): array
    {
        static $map = null;

        if ($map === null) {
            $map = [];

            foreach (self::cases() as $module) {
                foreach ($module->permissions() as $item) {
                    $map[$item->value][] = $module;
                }
            }
        }

        $value = $permission instanceof Permission ? $permission->value : $permission;

        return $map[$value] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Completa a seleção com os módulos exigidos pelos escolhidos.
     *
     * @param  iterable<self|string>  $modules
     * @return list<string>
     */
    public static function withRequirements(iterable $modules): array
    {
        $selected = [];

        foreach ($modules as $module) {
            $module = $module instanceof self ? $module : self::from($module);
            $selected[$module->value] = true;

            foreach ($module->requires() as $required) {
                $selected[$required->value] = true;
            }
        }

        // Mantém a ordem do catálogo para o valor persistido ser estável.
        return array_values(array_filter(
            self::values(),
            fn (string $value): bool => isset($selected[$value]),
        ));
    }

    /**
     * @return list<Permission>
     */
    private static function vehicleRegistry(): array
    {
        return [
            Permission::VEHICLE_CREATE,
            Permission::VEHICLE_READ,
            Permission::VEHICLE_UPDATE,
            Permission::VEHICLE_DELETE,
            Permission::VEHICLE_DATA_CONFIG_READ,
            Permission::VEHICLE_DATA_CONFIG_UPDATE,
        ];
    }

    /**
     * @return list<Permission>
     */
    private static function equipmentRegistry(): array
    {
        return [
            Permission::EQUIPMENT_CREATE,
            Permission::EQUIPMENT_READ,
            Permission::EQUIPMENT_UPDATE,
            Permission::EQUIPMENT_DELETE,
            Permission::EQUIPMENT_DETAILS_READ,
            Permission::STOCK_CREATE,
            Permission::STOCK_READ,
            Permission::STOCK_UPDATE,
            Permission::STOCK_DELETE,
        ];
    }

    /**
     * @return list<Permission>
     */
    private static function serviceCatalog(): array
    {
        return [
            Permission::SERVICE_CREATE,
            Permission::SERVICE_READ,
            Permission::SERVICE_UPDATE,
            Permission::SERVICE_DELETE,
        ];
    }
}
