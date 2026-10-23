<?php

namespace Tests\Concerns;

use App\Modules\ServiceOrder\Models\ServiceOrder;

trait SignsServiceOrders
{
    /**
     * Marca a OS como assinada pelo cliente. A assinatura é obrigatória para
     * concluir a OS, então testes que só exercitam a conclusão podem partir
     * desse estado em vez de percorrer o fluxo de captura no aplicativo.
     */
    protected function signServiceOrder(string $uuid): void
    {
        ServiceOrder::query()->where('uuid', $uuid)->update([
            'loan_term_accepted_at' => now(),
            'loan_term_signature_path' => "service-orders/{$uuid}/assinatura/test.png",
        ]);
    }
}
