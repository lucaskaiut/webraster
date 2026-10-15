<?php

use App\Modules\ContractEvidence\Support\EvidenceDatabaseGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Cadeia de evidências da assinatura de contratos.
 *
 * - contract_signing_sessions: sessão de assinatura controlada pelo servidor,
 *   com o snapshot exato do documento apresentado e o consentimento exibido.
 * - contract_signatures: registro imutável de cada assinatura (documento
 *   final, imagem, manifesto canônico e assinatura Ed25519 do manifesto).
 * - contract_evidence_events: trilha append-only encadeada por hash.
 *
 * As tabelas de evidência não usam FK em cascata: a evidência precisa
 * sobreviver à exclusão do contrato/cliente. Os gatilhos bloqueiam alteração
 * e exclusão diretamente no banco (defesa em profundidade além do modelo).
 * Contratos assinados antes desta migração não recebem evidências
 * retroativas: permanecem como estão e são identificados como legados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signing_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('client_contract_id')->index();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('auth_token_id')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedInteger('document_version');
            $table->string('presented_document_path');
            $table->string('presented_document_sha256', 64);
            $table->string('consent_version', 40);
            $table->text('consent_text');
            $table->string('consent_sha256', 64);
            $table->dateTime('expires_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('revoked_at', 6)->nullable();
            $table->string('revoked_reason', 60)->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('client_id');
            $table->uuid('client_uuid');
            $table->unsignedBigInteger('client_contract_id')->index();
            $table->uuid('client_contract_uuid');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable()->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('method', 40);
            $table->unsignedInteger('document_version');
            $table->string('presented_document_path')->nullable();
            $table->string('presented_document_sha256', 64)->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_sha256', 64)->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->string('document_media_type', 100)->nullable();
            $table->string('signature_image_path')->nullable();
            $table->string('signature_image_sha256', 64)->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_cpf_masked', 20)->nullable();
            $table->string('signer_cpf_commitment', 64)->nullable();
            $table->string('signer_birth_date_commitment', 64)->nullable();
            $table->boolean('cpf_matches_client')->nullable();
            $table->string('consent_version', 40)->nullable();
            $table->string('consent_sha256', 64)->nullable();
            $table->boolean('consent_explicit')->default(false);
            $table->dateTime('consented_at', 6)->nullable();
            $table->dateTime('signed_at', 6);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->longText('manifest');
            $table->string('manifest_sha256', 64);
            $table->string('events_head_hash', 64);
            $table->string('evidence_status', 20)->index();
            $table->text('manifest_signature')->nullable();
            $table->string('key_id', 64)->nullable();
            $table->dateTime('protected_at', 6)->nullable();
            $table->string('protection_error', 255)->nullable();
            $table->dateTime('superseded_at', 6)->nullable();
            $table->string('superseded_reason', 60)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('contract_evidence_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('client_contract_id');
            $table->uuid('client_contract_uuid');
            $table->unsignedInteger('sequence');
            $table->string('type', 60);
            $table->string('result', 20);
            $table->dateTime('occurred_at', 6);
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->unsignedBigInteger('signature_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->json('data')->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64);

            $table->unique(['client_contract_id', 'sequence']);
        });

        Schema::table('client_contracts', function (Blueprint $table) {
            $table->unsignedInteger('document_version')->default(1)->after('body');
            $table->unsignedBigInteger('current_signature_id')->nullable()->after('document_version');
        });

        $this->createGuards();
    }

    public function down(): void
    {
        $this->dropGuards();

        Schema::table('client_contracts', function (Blueprint $table) {
            $table->dropColumn(['document_version', 'current_signature_id']);
        });

        Schema::dropIfExists('contract_evidence_events');
        Schema::dropIfExists('contract_signatures');
        Schema::dropIfExists('contract_signing_sessions');
    }

    private function createGuards(): void
    {
        try {
            EvidenceDatabaseGuards::install();
        } catch (Throwable $e) {
            // Sem privilégio de TRIGGER (ex.: binlog sem SUPER) a proteção
            // continua na camada de modelo; instale depois com
            // php artisan contract-evidence:install-guards (usuário privilegiado).
            Log::warning('contract_evidence: gatilhos de imutabilidade não criados', ['error' => $e->getMessage()]);
        }
    }

    private function dropGuards(): void
    {
        EvidenceDatabaseGuards::drop();
    }
};
