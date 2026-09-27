<?php

namespace App\Modules\Chat\Jobs;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Crm\Services\CrmAiHandoffService;
use App\Modules\Crm\Services\CrmAiOrchestrator;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessCrmAiReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function __construct(public readonly int $conversationId)
    {
        $this->onQueue((string) config('chat.queue', 'chat'));
    }

    public function uniqueId(): string
    {
        return 'crm-ai-conversation-'.$this->conversationId;
    }

    public function handle(CrmAiOrchestrator $orchestrator, CrmAiHandoffService $handoff): void
    {
        $lock = Cache::lock('crm:ai:conversation:'.$this->conversationId, (int) config('chat.ai.lock_seconds', 120));

        if (! $lock->get()) {
            return;
        }

        $conversation = null;

        try {
            $conversation = Conversation::query()->with(['connection.tenant', 'lead', 'contact'])->find($this->conversationId);

            if ($conversation === null) {
                return;
            }

            TenantContext::set($conversation->connection->tenant);
            $orchestrator->respond($conversation);
        } catch (Throwable $e) {
            Log::error('crm.ai.reply_failed', [
                'conversation_id' => $this->conversationId,
                'message' => $e->getMessage(),
            ]);

            if ($conversation !== null) {
                TenantContext::set($conversation->connection->tenant);

                $handoff->escalate(
                    $conversation,
                    'Erro técnico ao processar resposta da IA: '.$e->getMessage(),
                    null,
                    'system',
                );
            }
        } finally {
            $lock->release();
        }
    }
}
