<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retenção de dados (limpeza agendada)
    |--------------------------------------------------------------------------
    |
    | ATENÇÃO: as tabelas que alimentam os relatórios de posição dos clientes
    | exigem retenção de NO MÍNIMO 6 MESES e nunca entram na limpeza:
    |
    |   gps_positions        — histórico de posições/paradas/percursos
    |   alerts               — relatório de eventos (excesso de velocidade)
    |   device_command_logs  — relatório de comandos enviados
    |
    | Apenas as tabelas abaixo são podadas pelo comando `tracking:prune`:
    |
    |   traccar_events        — fila de webhooks do Traccar; remove somente
    |                           status terminais (processed/ignored/failed).
    |                           pending/processing NUNCA são removidos.
    |   equipment_raw_logs    — log bruto interno de diagnóstico (suporte).
    |   tc_positions          — histórico bruto do gateway (banco "traccar");
    |                           não alimenta relatórios — a reconciliação do
    |                           painel puxa apenas a partir da última posição
    |                           já gravada em gps_positions.
    |
    */

    'traccar_events_days' => (int) env('PRUNE_TRACCAR_EVENTS_DAYS', 30),

    'equipment_raw_logs_days' => (int) env('PRUNE_EQUIPMENT_RAW_LOGS_DAYS', 30),

    'traccar_positions_days' => (int) env('PRUNE_TRACCAR_POSITIONS_DAYS', 60),

    /** Quantidade de linhas removidas por lote (evita locks longos). */
    'batch_size' => (int) env('PRUNE_BATCH_SIZE', 5000),

];
