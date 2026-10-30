<?php

namespace Database\Seeders\Demo;

use App\Modules\Chat\Models\Contact;
use App\Modules\Crm\Enums\LeadStatus;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pipeline;
use App\Modules\Crm\Models\PipelineStage;
use Illuminate\Database\Seeder;

/**
 * Funis comerciais com leads em todas as etapas (abertos, ganhos e perdidos).
 */
class DemoCrmSeeder extends Seeder
{
    private const NOTES = [
        'Pediu proposta para rastreamento com bloqueio.',
        'Tem frota terceirizada; quer relatório de jornada.',
        'Indicação de cliente atual.',
        'Interessado no plano anual.',
        'Quer integração com o ERP da empresa.',
        'Comparando com concorrente; sensível a preço.',
        'Retornar após o fechamento do mês.',
        'Solicitou demonstração do aplicativo.',
    ];

    public function run(DemoContext $ctx): void
    {
        $sales = $this->pipeline($ctx, 'Vendas', 'Funil padrão de novos clientes.', true, [
            ['Novo lead', '#6366F1', 'initial'],
            ['Qualificação', '#0EA5E9', null],
            ['Proposta enviada', '#F59E0B', null],
            ['Negociação', '#8B5CF6', null],
            ['Ganho', '#16A34A', 'won'],
            ['Perdido', '#DC2626', 'lost'],
        ]);

        $fleet = $this->pipeline($ctx, 'Frotas corporativas', 'Oportunidades com mais de 10 veículos.', false, [
            ['Contato inicial', '#6366F1', 'initial'],
            ['Visita técnica', '#0EA5E9', null],
            ['Proposta', '#F59E0B', null],
            ['Contrato fechado', '#16A34A', 'won'],
            ['Perdido', '#DC2626', 'lost'],
        ]);

        $this->leads($ctx, $sales, 24, false);
        $this->leads($ctx, $fleet, 9, true);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: ?string}>  $stages
     * @return array{pipeline: Pipeline, stages: list<PipelineStage>}
     */
    private function pipeline(DemoContext $ctx, string $name, string $description, bool $default, array $stages): array
    {
        $pipeline = Pipeline::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'name' => $name,
            'description' => $description,
            'active' => true,
            'is_default' => $default,
            'created_at' => $ctx->now->subMonths(8),
        ]);

        $created = [];

        foreach ($stages as $position => [$stageName, $color, $kind]) {
            $created[] = PipelineStage::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'pipeline_id' => $pipeline->getKey(),
                'name' => $stageName,
                'position' => $position,
                'color' => $color,
                'is_initial' => $kind === 'initial',
                'is_final' => in_array($kind, ['won', 'lost'], true),
                'is_won' => $kind === 'won',
                'is_lost' => $kind === 'lost',
                'active' => true,
            ]);
        }

        return ['pipeline' => $pipeline, 'stages' => $created];
    }

    /**
     * @param  array{pipeline: Pipeline, stages: list<PipelineStage>}  $funnel
     */
    private function leads(DemoContext $ctx, array $funnel, int $count, bool $companies): void
    {
        $faker = $ctx->faker;
        $owners = [$ctx->staff[0], $ctx->staff[1], $ctx->staff[2]];

        for ($i = 0; $i < $count; $i++) {
            $stage = $funnel['stages'][$i % count($funnel['stages'])];
            $name = $companies ? $faker->company() : $faker->name();
            $createdAt = $ctx->now->subDays($faker->numberBetween(1, 120))->setTime($faker->numberBetween(11, 22), $faker->numberBetween(0, 59));

            $contact = Contact::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'name' => $name,
                'phone' => '55'.$ctx->mobile(),
                'email' => $faker->boolean(60) ? $ctx->email($name, 'lead.localhost') : null,
                'created_at' => $createdAt,
            ]);

            Lead::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'contact_id' => $contact->getKey(),
                'pipeline_id' => $funnel['pipeline']->getKey(),
                'stage_id' => $stage->getKey(),
                'owner_user_id' => $stage->is_initial && $faker->boolean(50) ? null : $faker->randomElement($owners)->getKey(),
                'status' => match (true) {
                    $stage->is_won => LeadStatus::WON,
                    $stage->is_lost => LeadStatus::LOST,
                    default => LeadStatus::OPEN,
                },
                'source' => $faker->randomElement(['whatsapp', 'whatsapp', 'site', 'indicacao', 'instagram', 'google']),
                'score' => $faker->numberBetween(10, 95),
                'notes' => $faker->randomElement(self::NOTES),
                'ai_enabled' => $faker->boolean(70),
                'last_interaction_at' => $createdAt->addDays($faker->numberBetween(0, 10))->min($ctx->now),
                'created_at' => $createdAt,
            ]);
        }
    }
}
