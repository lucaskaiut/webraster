<?php

namespace Database\Seeders\Demo;

use App\Modules\Checklist\Enums\ChecklistAnswerType;
use App\Modules\Checklist\Enums\ServiceOrderChecklistStatus;
use App\Modules\Checklist\Models\Checklist;
use App\Modules\Checklist\Models\ServiceOrderChecklist;
use App\Modules\Checklist\Models\ServiceOrderChecklistAnswer;
use App\Modules\Checklist\Models\ServiceOrderChecklistOption;
use App\Modules\Checklist\Models\ServiceOrderChecklistQuestion;
use App\Modules\Client\Models\Client;
use App\Modules\Commission\Models\Commission;
use App\Modules\Commission\Services\CommissionService;
use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceBillingEvent;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Enums\ServiceOrderHistoryAction;
use App\Modules\ServiceOrder\Enums\ServiceOrderPriority;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderEquipmentTest;
use App\Modules\ServiceOrder\Models\ServiceOrderHistory;
use App\Modules\ServiceOrder\Models\ServiceOrderItem;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Ordens de serviço: histórico de instalações, manutenções, retiradas e
 * cancelamentos, a agenda de hoje (concluídas, em andamento e abertas) e
 * os próximos dias. Inclui itens, histórico, checklists, comissões, testes
 * de equipamento e cobranças avulsas das OS.
 */
class DemoServiceOrderSeeder extends Seeder
{
    private const SLOTS = [[8, 0], [10, 15], [13, 30], [15, 45]];

    private const INSTALL_LOCATIONS = [
        'Atrás do painel, lado do passageiro, fixado com abraçadeiras.',
        'Sob o banco do motorista, protegido por capa antichama.',
        'Dentro da coluna de direção, junto ao chicote original.',
        'Atrás do porta-luvas, preso ao suporte metálico.',
        'Na cabine, atrás do banco-leito (lado esquerdo).',
        'Sob o banco, próximo à bateria (moto).',
    ];

    private const CANCEL_REASONS = [
        'Cliente cancelou a visita por telefone.',
        'Veículo não estava no local combinado.',
        'Reagendada a pedido do cliente.',
        'Duplicidade de abertura.',
    ];

    private const MAINTENANCE_DESCRIPTIONS = [
        'maintenance' => ['Equipamento sem comunicação há mais de 24h.', 'Veículo não está atualizando posição no app.', 'Bloqueio não responde ao comando remoto.', 'Ignição sempre aparece como desligada.'],
        'swap' => ['Substituição do rastreador por modelo 4G.', 'Troca de equipamento com defeito na antena GPS.'],
        'preventive' => ['Revisão preventiva semestral da instalação.', 'Verificação de chicote e fixação após reclamação de ruído.'],
        'removal' => ['Retirada do equipamento por venda do veículo.', 'Retirada por encerramento de contrato.'],
    ];

    private int $number = 1000;

    /** @var array<string, true> */
    private array $usedSlots = [];

    /** @var array<int, true> técnicos com uma OS em andamento agora */
    private array $busyNow = [];

    /** @var list<array<string, mixed>> */
    private array $plans = [];

    public function run(DemoContext $ctx, CommissionService $commissions): void
    {
        $this->planInstallations($ctx);
        $this->planMaintenance($ctx);
        $this->planRemovals($ctx);
        $this->planToday($ctx);
        $this->planUpcoming($ctx);

        usort($this->plans, fn (array $a, array $b) => $a['created_at'] <=> $b['created_at']);

        foreach ($this->plans as $plan) {
            $this->createOrder($ctx, $commissions, $plan);
        }
    }

    private function planInstallations(DemoContext $ctx): void
    {
        foreach ($ctx->fleet as $entry) {
            if ($entry['installed_at'] === null) {
                continue;
            }

            $services = ['install'];

            if ($entry['usage'] !== 'site' && $ctx->faker->boolean(45)) {
                $services[] = 'install_block';
            }

            $this->plans[] = $this->plan($ctx, $entry, $services, ServiceOrderStatus::COMPLETED, $entry['installed_at'], $entry['installer'], [
                'description' => "Instalação de rastreador no veículo {$entry['vehicle']->plate}.",
                'install' => true,
            ]);
        }

        // Veículos aguardando instalação: uma em andamento hoje e o resto agendado.
        $pending = array_values(array_filter($ctx->fleet, fn ($entry) => $entry['signal'] === 'none' && $entry['client']->is_active));

        foreach ($pending as $index => $entry) {
            $day = $index === 0 ? $ctx->localNow()->startOfDay() : $this->businessDay($ctx->localNow(), $index);
            [$technician, $start] = $this->freeSlot($ctx, $day, $index === 0 ? 1 : null);

            if ($index === 0) {
                $this->busyNow[$technician->getKey()] = true;
            }

            $this->plans[] = $this->plan($ctx, $entry, ['install', 'install_block'], $index === 0 ? ServiceOrderStatus::IN_PROGRESS : ServiceOrderStatus::OPEN, $start, $technician, [
                'description' => "Instalação de rastreador e bloqueador no veículo {$entry['vehicle']->plate} (cliente novo).",
                'priority' => ServiceOrderPriority::HIGH,
                'created_at' => $ctx->now->subDays($ctx->faker->numberBetween(1, 4))->setTime(14, 20),
            ]);
        }
    }

    private function planMaintenance(DemoContext $ctx): void
    {
        $faker = $ctx->faker;
        $installed = array_values(array_filter($ctx->fleet, fn ($entry) => $entry['equipment'] !== null && $entry['client']->is_active));

        for ($i = 0; $i < 34; $i++) {
            $entry = $faker->randomElement($installed);
            $type = $faker->randomElement(['maintenance', 'maintenance', 'maintenance', 'swap', 'preventive', 'preventive']);
            $day = $this->businessDay($ctx->localNow()->subDays($faker->numberBetween(2, 100)), 0);

            if ($entry['installed_at'] !== null && $day->lessThan($entry['installed_at'])) {
                continue;
            }

            $status = match (true) {
                $i % 9 === 4 => ServiceOrderStatus::CANCELLED,
                default => ServiceOrderStatus::COMPLETED,
            };

            [$technician, $start] = $this->freeSlot($ctx, $day);
            $services = $i % 11 === 7 ? ['failed_visit'] : [$type];

            $this->plans[] = $this->plan($ctx, $entry, $services, $status, $start, $technician, [
                'description' => $faker->randomElement(self::MAINTENANCE_DESCRIPTIONS[$type]),
                'priority' => $faker->randomElement([ServiceOrderPriority::NORMAL, ServiceOrderPriority::NORMAL, ServiceOrderPriority::HIGH, ServiceOrderPriority::LOW, ServiceOrderPriority::URGENT]),
                'failed' => $services === ['failed_visit'],
            ]);
        }
    }

    private function planRemovals(DemoContext $ctx): void
    {
        foreach ($ctx->fleet as $entry) {
            if ($entry['client']->is_active || $entry['equipment'] === null) {
                continue;
            }

            $start = $ctx->now->subMonths(2)->setTime(13, 30);

            $this->plans[] = $this->plan($ctx, $entry, ['removal'], ServiceOrderStatus::COMPLETED, $start, $entry['installer'], [
                'description' => 'Retirada do equipamento por encerramento de contrato.',
            ]);
        }
    }

    /**
     * Agenda de hoje: cada técnico com uma OS concluída pela manhã, uma em
     * andamento e outras abertas à tarde.
     */
    private function planToday(DemoContext $ctx): void
    {
        $faker = $ctx->faker;
        $installed = array_values(array_filter($ctx->fleet, fn ($entry) => $entry['equipment'] !== null && $entry['client']->is_active));
        $today = $ctx->localNow()->startOfDay();

        foreach ($ctx->technicians as $techIndex => $technician) {
            foreach (self::SLOTS as $slotIndex => [$hour, $minute]) {
                $key = $this->slotKey($technician, $today, $slotIndex);

                if (isset($this->usedSlots[$key]) || ($techIndex + $slotIndex) % 4 === 3) {
                    continue;
                }

                $start = $today->setTime($hour, $minute);
                $status = match (true) {
                    $start->addMinutes(90)->lessThan($ctx->now) => ServiceOrderStatus::COMPLETED,
                    $start->lessThanOrEqualTo($ctx->now) => ServiceOrderStatus::IN_PROGRESS,
                    default => ServiceOrderStatus::OPEN,
                };

                if ($status === ServiceOrderStatus::IN_PROGRESS && isset($this->busyNow[$technician->getKey()])) {
                    continue;
                }

                $type = $faker->randomElement(['maintenance', 'preventive', 'swap']);
                $this->usedSlots[$key] = true;

                $this->plans[] = $this->plan($ctx, $faker->randomElement($installed), [$type], $status, $start, $technician, [
                    'description' => $faker->randomElement(self::MAINTENANCE_DESCRIPTIONS[$type]),
                    'created_at' => $ctx->now->subDays($faker->numberBetween(1, 6))->setTime(11, 10),
                ]);
            }
        }
    }

    private function planUpcoming(DemoContext $ctx): void
    {
        $faker = $ctx->faker;
        $installed = array_values(array_filter($ctx->fleet, fn ($entry) => $entry['equipment'] !== null && $entry['client']->is_active));

        for ($i = 0; $i < 18; $i++) {
            $entry = $faker->randomElement($installed);
            $type = $faker->randomElement(['maintenance', 'preventive', 'preventive', 'swap', 'removal']);
            [$technician, $start] = $this->freeSlot($ctx, $this->businessDay($ctx->localNow(), 1 + intdiv($i, 3)));

            $this->plans[] = $this->plan($ctx, $entry, [$type], ServiceOrderStatus::OPEN, $start, $i % 6 === 5 ? null : $technician, [
                'description' => $faker->randomElement(self::MAINTENANCE_DESCRIPTIONS[$type]),
                'priority' => $faker->randomElement([ServiceOrderPriority::NORMAL, ServiceOrderPriority::NORMAL, ServiceOrderPriority::LOW, ServiceOrderPriority::HIGH]),
                'created_at' => $ctx->now->subDays($faker->numberBetween(0, 5))->setTime($faker->numberBetween(8, 17), 5),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $services
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function plan(DemoContext $ctx, array $entry, array $services, ServiceOrderStatus $status, CarbonImmutable $start, ?User $technician, array $extra = []): array
    {
        $start = $start->utc();
        $createdAt = $extra['created_at'] ?? $start->subDays($ctx->faker->numberBetween(1, 6))->setTime($ctx->faker->numberBetween(8, 17), $ctx->faker->numberBetween(0, 59));

        return [
            'entry' => $entry,
            'services' => $services,
            'status' => $status,
            'start' => $start,
            'technician' => $technician,
            'created_at' => $createdAt->greaterThan($ctx->now) ? $ctx->now->subHour() : $createdAt,
            'description' => $extra['description'] ?? null,
            'priority' => $extra['priority'] ?? ServiceOrderPriority::NORMAL,
            'install' => $extra['install'] ?? false,
            'failed' => $extra['failed'] ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function createOrder(DemoContext $ctx, CommissionService $commissions, array $plan): void
    {
        $faker = $ctx->faker;
        $entry = $plan['entry'];
        /** @var Client $client */
        $client = $entry['client'];
        /** @var ServiceOrderStatus $status */
        $status = $plan['status'];
        /** @var CarbonImmutable $start */
        $start = $plan['start'];
        /** @var User|null $technician */
        $technician = $plan['technician'];
        $creator = $faker->randomElement([$ctx->staff[1], $ctx->staff[2], $ctx->staff[0]]);
        $services = array_map(fn (string $key) => $ctx->services[$key], $plan['services']);
        $duration = array_sum(array_map(fn (Service $service) => $service->estimated_minutes, $services));
        $travel = $faker->numberBetween(12, 45);
        $completedAt = $status === ServiceOrderStatus::COMPLETED ? $start->addMinutes($duration + $faker->numberBetween(-10, 35)) : null;
        $cancelledAt = $status === ServiceOrderStatus::CANCELLED ? $start->subHours($faker->numberBetween(2, 30)) : null;
        $loanTerm = $plan['install'] && $status === ServiceOrderStatus::COMPLETED ? (string) $ctx->tenant->equipment_loan_term : null;

        $order = ServiceOrder::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'number' => ++$this->number,
            'status' => $status,
            'priority' => $plan['priority'],
            'client_id' => $client->getKey(),
            'vehicle_id' => $entry['vehicle']->getKey(),
            'equipment_id' => $entry['equipment']?->getKey(),
            'technician_id' => $technician?->getKey(),
            'address' => $client->geocoded_address,
            'latitude' => $client->latitude,
            'longitude' => $client->longitude,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->addMinutes($duration),
            'estimated_duration_minutes' => $duration,
            'estimated_travel_minutes' => $technician ? $travel : null,
            'estimated_return_travel_minutes' => $technician ? $travel + $faker->numberBetween(-5, 10) : null,
            'travel_distance_meters' => $technician ? $travel * $faker->numberBetween(450, 700) : null,
            'return_travel_distance_meters' => $technician ? $travel * $faker->numberBetween(450, 700) : null,
            'reminder_sent_at' => $start->subDay()->setTime(18, 0)->lessThan($ctx->now) && $status !== ServiceOrderStatus::CANCELLED ? $start->subDay()->setTime(18, 0) : null,
            'description' => $plan['description'],
            'notes' => $faker->optional(0.35)->randomElement([
                'Ligar para o responsável 30 minutos antes.',
                'Veículo fica no pátio dos fundos; procurar o encarregado.',
                'Cliente pediu atendimento até as 11h.',
                'Levar equipamento reserva.',
            ]),
            'installation_location' => $plan['install'] && $completedAt ? $faker->randomElement(self::INSTALL_LOCATIONS) : null,
            'execution_notes' => $completedAt ? $this->executionNotes($ctx, $plan) : null,
            'loan_term_accepted_at' => $loanTerm ? $completedAt->subMinutes(5) : null,
            'loan_term_collected_by' => $loanTerm ? $technician?->getKey() : null,
            'loan_term_text' => $loanTerm,
            'loan_term_text_hash' => $loanTerm ? hash('sha256', $loanTerm) : null,
            'cancellation_reason' => $cancelledAt ? $faker->randomElement(self::CANCEL_REASONS) : null,
            'created_by' => $creator->getKey(),
            'completed_by' => $completedAt ? $technician?->getKey() : null,
            'cancelled_by' => $cancelledAt ? $creator->getKey() : null,
            'completed_at' => $completedAt,
            'cancelled_at' => $cancelledAt,
            'created_at' => $plan['created_at'],
            'updated_at' => $completedAt ?? $cancelledAt ?? $plan['created_at'],
        ]);

        foreach ($services as $service) {
            ServiceOrderItem::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'service_order_id' => $order->getKey(),
                'service_id' => $service->getKey(),
                'name' => $service->name,
                'quantity' => 1,
                'unit_amount_cents' => $service->amount_cents,
                'commission_type' => $service->commission_type,
                'commission_value' => $service->commission_value,
                'created_at' => $plan['created_at'],
            ]);
        }

        $this->history($ctx, $order, ServiceOrderHistoryAction::CREATED, $creator, $plan['created_at']);

        if ($technician !== null) {
            $this->history($ctx, $order, ServiceOrderHistoryAction::TECHNICIAN_CHANGED, $creator, $plan['created_at']->addMinutes(2), 'technician_id', null, $technician->getKey());
        }

        if (in_array($status, [ServiceOrderStatus::IN_PROGRESS, ServiceOrderStatus::COMPLETED], true)) {
            $this->history($ctx, $order, ServiceOrderHistoryAction::STATUS_CHANGED, $technician, $start, 'status', 'open', 'in_progress');
            $this->checklist($ctx, $order, $ctx->checklists['initial'], $technician, $start->addMinutes(5), true);
        }

        if ($completedAt !== null) {
            $this->checklist($ctx, $order, $ctx->checklists['final'], $technician, $completedAt->subMinutes(3), ! $plan['failed']);

            if ($plan['install']) {
                $this->equipmentTests($ctx, $order, $entry, $technician, $completedAt->subMinutes(15));
                $this->history($ctx, $order, ServiceOrderHistoryAction::LOAN_TERM_ACCEPTED, $technician, $completedAt->subMinutes(5), 'loan_term_acceptance', null, 'Assinatura registrada', [
                    'accepted_at' => $completedAt->subMinutes(5)->toIso8601String(),
                    'term_hash' => $order->loan_term_text_hash,
                ]);
            }

            $this->history($ctx, $order, ServiceOrderHistoryAction::COMPLETED, $technician, $completedAt, 'status', 'in_progress', 'completed');
        }

        if ($cancelledAt !== null) {
            $this->history($ctx, $order, ServiceOrderHistoryAction::CANCELLED, $creator, $cancelledAt, 'status', 'open', 'cancelled', [
                'cancellation_reason' => $order->cancellation_reason,
            ]);
        }

        if ($technician !== null) {
            $commission = $commissions->syncForOrder($order);

            Commission::query()->whereKey($commission->getKey())->update([
                'finalized_at' => $completedAt,
                'created_at' => $plan['created_at'],
                'updated_at' => $completedAt ?? $plan['created_at'],
            ]);
        }

        if ($completedAt !== null && $client->is_active && $faker->boolean(65)) {
            $this->billing($ctx, $order, $services, $completedAt);
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function executionNotes(DemoContext $ctx, array $plan): string
    {
        if ($plan['failed']) {
            return 'Técnico compareceu no horário, mas o veículo não estava disponível. Cliente ciente da cobrança da visita.';
        }

        if ($plan['install']) {
            return $ctx->faker->randomElement([
                'Instalação concluída, equipamento comunicando e bloqueio testado com sucesso.',
                'Instalado e testado. Cliente orientado sobre o aplicativo.',
                'Instalação sem intercorrências; posição validada com a central.',
            ]);
        }

        return $ctx->faker->randomElement([
            'Conector da alimentação oxidado; refeito o chicote e equipamento voltou a comunicar.',
            'Chip sem sinal; substituído por linha de outra operadora.',
            'Antena GPS reposicionada para melhor recepção.',
            'Revisão realizada, fixação reforçada e teste de bloqueio OK.',
            'Equipamento substituído; antigo encaminhado para análise (RMA).',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function history(
        DemoContext $ctx,
        ServiceOrder $order,
        ServiceOrderHistoryAction $action,
        ?User $user,
        CarbonImmutable $at,
        ?string $field = null,
        mixed $old = null,
        mixed $new = null,
        ?array $meta = null,
    ): void {
        ServiceOrderHistory::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'service_order_id' => $order->getKey(),
            'user_id' => $user?->getKey(),
            'action' => $action,
            'field' => $field,
            'old_value' => $old !== null ? (string) $old : null,
            'new_value' => $new !== null ? (string) $new : null,
            'meta' => $meta,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function checklist(DemoContext $ctx, ServiceOrder $order, Checklist $template, ?User $technician, CarbonImmutable $at, bool $completed): void
    {
        $faker = $ctx->faker;

        $checklist = ServiceOrderChecklist::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'service_order_id' => $order->getKey(),
            'checklist_id' => $template->getKey(),
            'type' => $template->type,
            'name' => $template->name,
            'description' => $template->description,
            'status' => $completed ? ServiceOrderChecklistStatus::COMPLETED : ServiceOrderChecklistStatus::PENDING,
            'completed_at' => $completed ? $at : null,
            'completed_by' => $completed ? $technician?->getKey() : null,
            'created_at' => $order->created_at,
        ]);

        foreach ($template->questions as $templateQuestion) {
            $question = ServiceOrderChecklistQuestion::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'service_order_checklist_id' => $checklist->getKey(),
                'checklist_question_id' => $templateQuestion->getKey(),
                'prompt' => $templateQuestion->prompt,
                'answer_type' => $templateQuestion->answer_type,
                'photo_rule' => $templateQuestion->photo_rule,
                'position' => $templateQuestion->position,
            ]);

            $options = [];

            foreach ($templateQuestion->options as $templateOption) {
                $options[] = ServiceOrderChecklistOption::query()->create([
                    'tenant_id' => $ctx->tenant->getKey(),
                    'service_order_checklist_question_id' => $question->getKey(),
                    'checklist_question_option_id' => $templateOption->getKey(),
                    'label' => $templateOption->label,
                    'position' => $templateOption->position,
                ]);
            }

            if (! $completed) {
                continue;
            }

            $type = $templateQuestion->answer_type instanceof ChecklistAnswerType
                ? $templateQuestion->answer_type
                : ChecklistAnswerType::from((string) $templateQuestion->answer_type);

            $answer = ServiceOrderChecklistAnswer::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'service_order_checklist_question_id' => $question->getKey(),
                'text_answer' => $type === ChecklistAnswerType::TEXT ? $this->textAnswer($ctx, $templateQuestion->prompt, $order) : null,
                'answered_at' => $at,
                'answered_by' => $technician?->getKey(),
            ]);

            if ($options === []) {
                continue;
            }

            $selected = $type === ChecklistAnswerType::MULTIPLE_CHOICE
                ? $faker->randomElements($options, $faker->numberBetween(2, count($options)))
                : [$faker->boolean(80) ? $options[0] : $faker->randomElement($options)];

            $answer->selectedOptions()->attach(array_map(fn (ServiceOrderChecklistOption $option) => $option->getKey(), $selected));
        }
    }

    private function textAnswer(DemoContext $ctx, string $prompt, ServiceOrder $order): string
    {
        return match (true) {
            str_contains($prompt, 'Quilometragem') => number_format($ctx->faker->numberBetween(10_000, 280_000), 0, ',', '.').' km',
            str_contains($prompt, 'Local de instalação') => $order->installation_location ?? 'Mantido o local original da instalação.',
            default => $ctx->faker->randomElement(['Sem observações.', 'Veículo em bom estado geral.', 'Cliente acompanhou o serviço.', 'Pequeno risco no para-choque traseiro, já existente.']),
        };
    }

    /**
     * Testes de bloqueio/desbloqueio feitos pelo técnico ao final da instalação.
     *
     * @param  array<string, mixed>  $entry
     */
    private function equipmentTests(DemoContext $ctx, ServiceOrder $order, array $entry, ?User $technician, CarbonImmutable $at): void
    {
        if ($entry['equipment'] === null) {
            return;
        }

        foreach ([['block', 'engineStop', 0], ['unblock', 'engineResume', 2]] as [$action, $command, $offset]) {
            $requestedAt = $at->addMinutes($offset);

            $log = DeviceCommandLog::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'vehicle_id' => $entry['vehicle']->getKey(),
                'equipment_id' => $entry['equipment']->getKey(),
                'service_order_id' => $order->getKey(),
                'user_id' => $technician?->getKey(),
                'command_type' => $command,
                'payload' => ['type' => $command, 'attributes' => []],
                'status' => DeviceCommandStatus::SUCCESS,
                'requested_at' => $requestedAt,
                'executed_at' => $requestedAt->addSeconds(4),
                'response' => ['http_status' => 200, 'body' => ['result' => 'Command sent']],
                'created_at' => $requestedAt,
            ]);

            ServiceOrderEquipmentTest::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'service_order_id' => $order->getKey(),
                'equipment_id' => $entry['equipment']->getKey(),
                'user_id' => $technician?->getKey(),
                'device_command_log_id' => $log->getKey(),
                'action' => $action,
                'command_type' => $command,
                'status' => DeviceCommandStatus::SUCCESS,
                'result' => ['http_status' => 200],
                'requested_at' => $requestedAt,
                'executed_at' => $requestedAt->addSeconds(4),
                'created_at' => $requestedAt,
            ]);

            $this->history($ctx, $order, ServiceOrderHistoryAction::EQUIPMENT_TESTED, $technician, $requestedAt->addSeconds(5), 'equipment_test', null, $action, [
                'action' => $action,
                'command_type' => $command,
                'status' => DeviceCommandStatus::SUCCESS->value,
                'equipment_id' => $entry['equipment']->uuid,
            ]);
        }
    }

    /**
     * @param  list<Service>  $services
     */
    private function billing(DemoContext $ctx, ServiceOrder $order, array $services, CarbonImmutable $completedAt): void
    {
        $faker = $ctx->faker;
        $amount = array_sum(array_map(fn (Service $service) => $service->amount_cents, $services));
        $due = $completedAt->addDays(10)->startOfDay();
        $billedAt = $completedAt->addHour();
        $status = match (true) {
            $due->lessThan($ctx->now->subDays(3)) => $faker->boolean(90) ? BillingStatus::PAID : BillingStatus::OVERDUE,
            default => BillingStatus::PENDING,
        };
        $paidAt = $status === BillingStatus::PAID ? $due->subDays($faker->numberBetween(0, 8))->setTime($faker->numberBetween(8, 20), $faker->numberBetween(0, 59)) : null;
        $paidAt = $paidAt?->lessThan($billedAt) ? $billedAt->addHours(5) : $paidAt;

        $billing = FinanceBilling::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'number' => $ctx->nextBillingNumber(),
            'service_order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'status' => $status,
            'payment_method' => $paidAt ? $faker->randomElement([PaymentMethod::PIX, PaymentMethod::BOLETO]) : null,
            'payment_gateway' => 'asaas',
            'amount_cents' => $amount,
            'paid_amount_cents' => $paidAt ? $amount : null,
            'due_at' => $due->toDateString(),
            'paid_at' => $paidAt,
            'description' => sprintf('OS #%d — %s', $order->number, implode(', ', array_map(fn (Service $service) => $service->name, $services))),
            'created_at' => $billedAt,
            'updated_at' => $paidAt ?? $billedAt,
        ]);

        foreach (array_filter([
            ['created', null, BillingStatus::PENDING, $billedAt],
            $status === BillingStatus::OVERDUE ? ['marked_overdue', BillingStatus::PENDING, BillingStatus::OVERDUE, $due->addDay()->setTime(3, 0)] : null,
            $paidAt ? ['paid', BillingStatus::PENDING, BillingStatus::PAID, $paidAt] : null,
        ]) as [$action, $from, $to, $at]) {
            FinanceBillingEvent::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'billing_id' => $billing->getKey(),
                'action' => $action,
                'from_status' => $from?->value,
                'to_status' => $to->value,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $this->history($ctx, $order, ServiceOrderHistoryAction::BILLING_GENERATED, $ctx->staff[3], $billedAt, 'billing', null, "#{$billing->number}", [
            'billing_uuid' => $billing->uuid,
        ]);
    }

    /**
     * Encontra um técnico com horário livre no dia (ou no slot pedido).
     * Trabalha no fuso local; plan() converte o início para UTC.
     *
     * @return array{0: User, 1: CarbonImmutable}
     */
    private function freeSlot(DemoContext $ctx, CarbonImmutable $day, ?int $preferredSlot = null): array
    {
        $technicians = $ctx->faker->shuffleArray($ctx->technicians);
        $slots = $preferredSlot !== null ? [$preferredSlot] : $ctx->faker->shuffleArray(array_keys(self::SLOTS));

        foreach ($slots as $slot) {
            foreach ($technicians as $technician) {
                $key = $this->slotKey($technician, $day, $slot);

                if (! isset($this->usedSlots[$key])) {
                    $this->usedSlots[$key] = true;
                    [$hour, $minute] = self::SLOTS[$slot];

                    return [$technician, $day->setTime($hour, $minute)];
                }
            }
        }

        return $this->freeSlot($ctx, $this->businessDay($day, 1));
    }

    private function slotKey(User $technician, CarbonImmutable $day, int $slot): string
    {
        return "{$technician->getKey()}:{$day->toDateString()}:{$slot}";
    }

    /**
     * Dia útil a $offset dias úteis de $from (0 = o próprio dia, ou o
     * próximo dia útil se cair no fim de semana).
     */
    private function businessDay(CarbonImmutable $from, int $offset): CarbonImmutable
    {
        $day = $from->startOfDay();

        while ($day->isWeekend()) {
            $day = $day->addDay();
        }

        for ($i = 0; $i < $offset; $i++) {
            do {
                $day = $day->addDay();
            } while ($day->isWeekend());
        }

        return $day;
    }
}
