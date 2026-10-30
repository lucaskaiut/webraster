<?php

namespace Database\Seeders\Demo;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Checklist\Enums\ChecklistAnswerType;
use App\Modules\Checklist\Enums\ChecklistPhotoRule;
use App\Modules\Checklist\Enums\ChecklistType;
use App\Modules\Checklist\Models\Checklist;
use App\Modules\Checklist\Models\ChecklistQuestion;
use App\Modules\Checklist\Models\ChecklistQuestionOption;
use App\Modules\Contract\Models\Contract;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Poi\Models\PoiCategory;
use App\Modules\Service\Enums\CommissionType;
use App\Modules\Service\Models\Service;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Shared\Support\Document;
use App\Modules\Stock\Models\Stock;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserWorkHour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Estoques, técnicos, serviços, planos, modelo de contrato, checklists e
 * categorias de pontos de interesse do tenant de demonstração.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(DemoContext $ctx): void
    {
        $tenantId = $ctx->tenant->getKey();
        $since = $ctx->now->subMonths(13);

        $ctx->stocks['central'] = Stock::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Estoque Central — Curitiba',
            'description' => 'Almoxarifado da matriz (Rebouças).',
            'is_active' => true,
            'created_at' => $since,
        ]);

        $ctx->stocks['araucaria'] = Stock::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Estoque Filial — Araucária',
            'description' => 'Ponto de apoio para a região metropolitana sul.',
            'is_active' => true,
            'created_at' => $since,
        ]);

        $this->seedTechnicians($ctx);
        $this->seedServices($ctx);
        $this->seedFinancePlans($ctx);
        $this->seedContract($ctx);
        $this->seedChecklists($ctx);
        $this->seedPoiCategories($ctx);
    }

    private function seedTechnicians(DemoContext $ctx): void
    {
        $password = Hash::make(DemoContext::PASSWORD);

        $technicians = [
            ['João Batista Ferreira', 'portao', 'Rua João Bettega', '1250', 'Portão', '81070000', true, true],
            ['Lucas Andrade', 'boqueirao', 'Avenida Marechal Floriano Peixoto', '7800', 'Boqueirão', '81730000', true, true],
            ['Fernando Souza', 'santa_felicidade', 'Avenida Manoel Ribas', '5600', 'Santa Felicidade', '82020000', true, false],
            ['Diego Martins', 'araucaria', 'Avenida Victor do Amaral', '900', 'Centro', '83702000', true, false],
            ['Paulo Henrique Alves', 'bacacheri', 'Rua Rio Tocantins', '310', 'Bacacheri', '82510000', false, false],
        ];

        foreach ($technicians as [$name, $base, $street, $number, $neighborhood, $zip, $active, $saturday]) {
            $firstName = explode(' ', $name)[0];
            $point = $ctx->near($ctx->place($base)['lat'], $ctx->place($base)['lng'], 400);
            $city = $base === 'araucaria' ? 'Araucária' : 'Curitiba';

            $stock = Stock::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'name' => "Técnico — {$name}",
                'description' => "Estoque em posse do técnico {$firstName}.",
                'is_active' => $active,
                'created_at' => $ctx->now->subMonths(12),
            ]);

            $user = new User([
                'name' => $name,
                'email' => $ctx->email($name, 'rastrosul.localhost'),
                'phone' => $ctx->mobile(),
                'document' => Document::fakeCpf(),
                'password' => $password,
                'type' => UserType::TECHNICIAN->value,
                'zip' => $zip,
                'street' => $street,
                'number' => $number,
                'neighborhood' => $neighborhood,
                'city' => $city,
                'state' => 'PR',
                'latitude' => $point['lat'],
                'longitude' => $point['lng'],
                'technician_active' => $active,
                'stock_id' => $stock->getKey(),
            ]);
            $user->forceFill([
                'tenant_id' => $ctx->tenant->getKey(),
                'created_at' => $ctx->now->subMonths(12),
            ])->save();
            $user->assignRole($ctx->roles[DefaultRole::TECHNICIAN->value]);

            foreach (range(1, 5) as $weekday) {
                UserWorkHour::query()->create([
                    'user_id' => $user->getKey(),
                    'weekday' => $weekday,
                    'starts_at' => '08:00',
                    'ends_at' => '18:00',
                ]);
            }

            if ($saturday) {
                UserWorkHour::query()->create([
                    'user_id' => $user->getKey(),
                    'weekday' => 6,
                    'starts_at' => '08:00',
                    'ends_at' => '12:00',
                ]);
            }

            $ctx->stocks["technician:{$user->getKey()}"] = $stock;

            if ($active) {
                $ctx->technicians[] = $user;
            }
        }
    }

    private function seedServices(DemoContext $ctx): void
    {
        $services = [
            'install' => ['Instalação de rastreador', 15000, 60, CommissionType::PERCENTAGE, 3000],
            'install_block' => ['Instalação de bloqueador', 8000, 30, CommissionType::FIXED, 2000],
            'removal' => ['Retirada de rastreador', 9000, 40, CommissionType::FIXED, 2500],
            'maintenance' => ['Manutenção corretiva', 12000, 60, CommissionType::PERCENTAGE, 2500],
            'swap' => ['Troca de equipamento', 11000, 50, CommissionType::FIXED, 3500],
            'preventive' => ['Revisão preventiva', 7000, 30, CommissionType::PERCENTAGE, 2000],
            'failed_visit' => ['Visita técnica improdutiva', 5000, 30, null, 0],
            'monthly' => ['Monitoramento veicular — mensal', 5990, 30, null, 0],
            'monthly_block' => ['Monitoramento com bloqueio — mensal', 7990, 30, null, 0],
            'fleet' => ['Monitoramento de frota — por veículo', 4990, 30, null, 0],
            'telemetry' => ['Telemetria avançada — mensal', 2990, 30, null, 0],
        ];

        foreach ($services as $key => [$name, $amount, $minutes, $commissionType, $commissionValue]) {
            $ctx->services[$key] = Service::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'name' => $name,
                'amount_cents' => $amount,
                'estimated_minutes' => $minutes,
                'commission_type' => $commissionType,
                'commission_value' => $commissionValue,
                'created_at' => $ctx->now->subMonths(13),
            ]);
        }

        // Ao lançar uma instalação, sugere também o bloqueador.
        $ctx->services['install']->linkedServices()->attach($ctx->services['install_block']->getKey());
        $ctx->services['swap']->linkedServices()->attach($ctx->services['preventive']->getKey());
    }

    private function seedFinancePlans(DemoContext $ctx): void
    {
        $plans = [
            'essential' => ['Essencial', 'Rastreamento 24h com app e alertas.', 5990, BillingPeriodicity::MONTHLY, 1, true],
            'block' => ['Essencial + Bloqueio', 'Rastreamento com bloqueio remoto do veículo.', 7990, BillingPeriodicity::MONTHLY, 1, true],
            'family' => ['Família', 'Até 3 veículos da mesma família.', 14990, BillingPeriodicity::MONTHLY, 3, true],
            'annual' => ['Essencial Anual', 'Plano Essencial com pagamento anual (2 meses grátis).', 59900, BillingPeriodicity::ANNUAL, 1, true],
            'legacy' => ['Básico (descontinuado)', 'Plano antigo, mantido apenas para clientes legados.', 4990, BillingPeriodicity::MONTHLY, 1, false],
        ];

        foreach ($plans as $key => [$name, $description, $amount, $periodicity, $limit, $active]) {
            $ctx->financePlans[$key] = FinancePlan::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'name' => $name,
                'description' => $description,
                'amount_cents' => $amount,
                'periodicity' => $periodicity,
                'device_limit' => $limit,
                'is_active' => $active,
                'created_at' => $ctx->now->subMonths(13),
            ]);
        }
    }

    private function seedContract(DemoContext $ctx): void
    {
        $body = <<<'HTML'
<div style="text-align:center">{{LOGO_EMPRESA}}</div>
<h2 style="text-align:center">CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE RASTREAMENTO E MONITORAMENTO VEICULAR</h2>
<p><strong>CONTRATADA:</strong> Rastro Sul Rastreamento Ltda., inscrita no CNPJ sob o nº 45.723.174/0001-10, com sede em Curitiba/PR.</p>
<p><strong>CONTRATANTE:</strong> {{NOME_CLIENTE}}, inscrito(a) no CPF/CNPJ sob o nº {{DOCUMENTO_CLIENTE}}, com endereço em {{ENDERECO_CLIENTE}}, e-mail {{EMAIL_CLIENTE}} e telefone {{TELEFONE_CLIENTE}}.</p>
<h3>1. DO OBJETO</h3>
<p>1.1. O presente contrato tem por objeto a prestação de serviços de rastreamento e monitoramento veicular via GPS/GPRS, com acesso à plataforma web e ao aplicativo móvel, para os veículos e serviços relacionados abaixo:</p>
{{TABELA_PEDIDO}}
<h3>2. DOS EQUIPAMENTOS</h3>
<p>2.1. Os equipamentos instalados são cedidos em regime de comodato e permanecem de propriedade da CONTRATADA, devendo ser devolvidos ao término do contrato.</p>
<p>2.2. A instalação, manutenção e retirada dos equipamentos serão realizadas exclusivamente por técnicos credenciados pela CONTRATADA.</p>
<h3>3. DO PAGAMENTO</h3>
<p>3.1. A CONTRATANTE pagará mensalmente os valores descritos na tabela acima, por boleto bancário, PIX ou cartão de crédito, na data de vencimento escolhida.</p>
<p>3.2. O atraso no pagamento implicará multa de 2% (dois por cento) e juros de 1% (um por cento) ao mês, podendo os serviços ser suspensos após 5 (cinco) dias de atraso.</p>
<h3>4. DAS RESPONSABILIDADES</h3>
<p>4.1. O serviço de rastreamento não constitui seguro, não garantindo a recuperação do veículo em caso de furto ou roubo.</p>
<p>4.2. A CONTRATADA não se responsabiliza por falhas decorrentes da indisponibilidade das redes de telefonia celular ou do sinal de satélites GPS.</p>
<h3>5. DA VIGÊNCIA</h3>
<p>5.1. Este contrato vigora por 12 (doze) meses a contar da assinatura, renovando-se automaticamente por iguais períodos, salvo manifestação contrária de qualquer das partes com 30 (trinta) dias de antecedência.</p>
<h3>6. DO FORO</h3>
<p>6.1. Fica eleito o foro da Comarca de Curitiba/PR para dirimir quaisquer dúvidas oriundas deste contrato.</p>
<table style="width:100%;margin-top:48px"><tr>
<td style="width:50%;text-align:center">{{ASSINATURA_EMPRESA}}<br/>Rastro Sul Rastreamento Ltda.</td>
<td style="width:50%;text-align:center">{{ASSINATURA_CLIENTE}}<br/>{{NOME_CLIENTE}}</td>
</tr></table>
HTML;

        $ctx->contract = Contract::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'name' => 'Contrato de rastreamento veicular (padrão)',
            'body' => $body,
            'created_at' => $ctx->now->subMonths(13),
        ]);

        Contract::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'name' => 'Contrato de frota corporativa',
            'body' => str_replace(
                'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE RASTREAMENTO E MONITORAMENTO VEICULAR',
                'CONTRATO DE GESTÃO E MONITORAMENTO DE FROTA CORPORATIVA',
                $body,
            ),
            'created_at' => $ctx->now->subMonths(10),
        ]);
    }

    private function seedChecklists(DemoContext $ctx): void
    {
        $checklists = [
            'initial' => [
                'Vistoria de entrada do veículo',
                'Registro das condições do veículo antes de iniciar o serviço.',
                ChecklistType::INITIAL,
                [
                    ['O veículo apresenta avarias externas?', ChecklistAnswerType::SINGLE_CHOICE, ChecklistPhotoRule::OPTIONAL, ['Não', 'Sim, leves', 'Sim, graves']],
                    ['Itens funcionando antes do serviço', ChecklistAnswerType::MULTIPLE_CHOICE, ChecklistPhotoRule::NONE, ['Faróis', 'Lanternas', 'Painel', 'Vidros elétricos', 'Travas elétricas', 'Som/multimídia']],
                    ['Nível de combustível', ChecklistAnswerType::SINGLE_CHOICE, ChecklistPhotoRule::NONE, ['Reserva', '1/4', '1/2', '3/4', 'Cheio']],
                    ['Quilometragem atual do odômetro', ChecklistAnswerType::TEXT, ChecklistPhotoRule::OPTIONAL, []],
                    ['Observações gerais', ChecklistAnswerType::TEXT, ChecklistPhotoRule::NONE, []],
                ],
            ],
            'final' => [
                'Checklist de entrega',
                'Validação do equipamento e orientação ao cliente ao final do serviço.',
                ChecklistType::FINAL,
                [
                    ['Local de instalação do equipamento', ChecklistAnswerType::TEXT, ChecklistPhotoRule::OPTIONAL, []],
                    ['Teste de comunicação (GPRS) realizado?', ChecklistAnswerType::SINGLE_CHOICE, ChecklistPhotoRule::NONE, ['Sim, comunicando', 'Sim, sem comunicação', 'Não realizado']],
                    ['Teste de bloqueio', ChecklistAnswerType::SINGLE_CHOICE, ChecklistPhotoRule::NONE, ['Bloqueio OK', 'Falhou', 'Não se aplica']],
                    ['Itens conferidos após o serviço', ChecklistAnswerType::MULTIPLE_CHOICE, ChecklistPhotoRule::NONE, ['Painel', 'Ignição', 'Partida', 'Faróis', 'Acabamentos recolocados']],
                    ['Cliente orientado sobre o uso do aplicativo?', ChecklistAnswerType::SINGLE_CHOICE, ChecklistPhotoRule::NONE, ['Sim', 'Não, cliente ausente']],
                    ['Observações finais', ChecklistAnswerType::TEXT, ChecklistPhotoRule::NONE, []],
                ],
            ],
        ];

        foreach ($checklists as $key => [$name, $description, $type, $questions]) {
            $checklist = Checklist::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'is_active' => true,
                'created_at' => $ctx->now->subMonths(12),
            ]);

            foreach ($questions as $position => [$prompt, $answerType, $photoRule, $options]) {
                $question = ChecklistQuestion::query()->create([
                    'tenant_id' => $ctx->tenant->getKey(),
                    'checklist_id' => $checklist->getKey(),
                    'prompt' => $prompt,
                    'answer_type' => $answerType,
                    'photo_rule' => $photoRule,
                    'position' => $position,
                ]);

                foreach ($options as $optionPosition => $label) {
                    ChecklistQuestionOption::query()->create([
                        'tenant_id' => $ctx->tenant->getKey(),
                        'checklist_question_id' => $question->getKey(),
                        'label' => $label,
                        'position' => $optionPosition,
                    ]);
                }
            }

            $ctx->checklists[$key] = $checklist->load('questions.options');
        }
    }

    private function seedPoiCategories(DemoContext $ctx): void
    {
        $categories = [
            'base' => ['Base / Garagem', '#2563EB'],
            'customer' => ['Cliente', '#16A34A'],
            'fuel' => ['Posto de combustível', '#F59E0B'],
            'workshop' => ['Oficina', '#DC2626'],
            'delivery' => ['Ponto de entrega', '#7C3AED'],
        ];

        $order = 0;

        foreach ($categories as $slug => [$name, $color]) {
            $ctx->poiCategories[$slug] = PoiCategory::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'name' => $name,
                'slug' => $slug,
                'color' => $color,
                'is_active' => true,
                'sort_order' => $order++,
            ]);
        }
    }
}
