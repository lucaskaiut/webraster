<?php

namespace App\Modules\ContractEvidence\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Gatilhos que tornam as tabelas de evidência imutáveis no próprio banco
 * (defesa em profundidade além do modelo). No MySQL com binlog, criar
 * gatilhos exige SUPER ou log_bin_trust_function_creators=1.
 */
final class EvidenceDatabaseGuards
{
    public const TRIGGERS = [
        'contract_signatures_no_update',
        'contract_signatures_no_delete',
        'contract_evidence_events_no_update',
        'contract_evidence_events_no_delete',
    ];

    /** Colunas de contract_signatures que só podem ser preenchidas uma vez. */
    private const WRITE_ONCE = ['manifest_signature', 'key_id', 'protected_at', 'superseded_at', 'superseded_reason'];

    /** Colunas de contract_signatures que nunca mudam após a criação. */
    private const IMMUTABLE = [
        'uuid', 'tenant_id', 'client_id', 'client_uuid', 'client_contract_id', 'client_contract_uuid', 'contract_id',
        'session_id', 'user_id', 'method', 'document_version', 'presented_document_path', 'presented_document_sha256',
        'document_path', 'document_sha256', 'document_size', 'document_media_type', 'signature_image_path',
        'signature_image_sha256', 'signer_name', 'signer_cpf_masked', 'signer_cpf_commitment', 'signer_birth_date_commitment',
        'cpf_matches_client', 'consent_version', 'consent_sha256', 'consent_explicit', 'consented_at', 'signed_at', 'ip',
        'user_agent', 'app_version', 'request_id', 'manifest', 'manifest_sha256', 'events_head_hash', 'created_at',
    ];

    public static function supported(?ConnectionInterface $connection = null): bool
    {
        return in_array(($connection ?? DB::connection())->getDriverName(), ['mysql', 'mariadb', 'sqlite'], true);
    }

    public static function install(?ConnectionInterface $connection = null): void
    {
        $connection ??= DB::connection();

        if (! self::supported($connection)) {
            return;
        }

        self::drop($connection);

        $statements = $connection->getDriverName() === 'sqlite' ? self::sqlite() : self::mysql();

        foreach ($statements as $sql) {
            $connection->unprepared($sql);
        }
    }

    public static function drop(?ConnectionInterface $connection = null): void
    {
        $connection ??= DB::connection();

        if (! self::supported($connection)) {
            return;
        }

        foreach (self::TRIGGERS as $trigger) {
            $connection->unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    public static function installed(?ConnectionInterface $connection = null): bool
    {
        $connection ??= DB::connection();

        $names = match ($connection->getDriverName()) {
            'sqlite' => array_column(
                array_map(fn ($row) => (array) $row, $connection->select("SELECT name FROM sqlite_master WHERE type = 'trigger'")),
                'name',
            ),
            'mysql', 'mariadb' => array_column(
                array_map(fn ($row) => (array) $row, $connection->select('SELECT TRIGGER_NAME AS name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')),
                'name',
            ),
            default => [],
        };

        return array_diff(self::TRIGGERS, $names) === [];
    }

    /**
     * @return list<string>
     */
    private static function mysql(): array
    {
        $immutable = implode(' AND ', array_map(fn ($c) => "OLD.`{$c}` <=> NEW.`{$c}`", self::IMMUTABLE));
        $writeOnce = implode(' OR ', array_map(
            fn ($c) => "(OLD.`{$c}` IS NOT NULL AND NOT (OLD.`{$c}` <=> NEW.`{$c}`))",
            self::WRITE_ONCE,
        ));

        return [
            "CREATE TRIGGER contract_signatures_no_update BEFORE UPDATE ON contract_signatures FOR EACH ROW
            BEGIN
                IF NOT ({$immutable}) OR {$writeOnce} THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contract_signatures: evidencia imutavel';
                END IF;
            END",
            "CREATE TRIGGER contract_signatures_no_delete BEFORE DELETE ON contract_signatures FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contract_signatures: evidencia imutavel'",
            "CREATE TRIGGER contract_evidence_events_no_update BEFORE UPDATE ON contract_evidence_events FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contract_evidence_events: trilha append-only'",
            "CREATE TRIGGER contract_evidence_events_no_delete BEFORE DELETE ON contract_evidence_events FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contract_evidence_events: trilha append-only'",
        ];
    }

    /**
     * @return list<string>
     */
    private static function sqlite(): array
    {
        $immutable = implode(' AND ', array_map(fn ($c) => "OLD.\"{$c}\" IS NEW.\"{$c}\"", self::IMMUTABLE));
        $writeOnce = implode(' OR ', array_map(
            fn ($c) => "(OLD.\"{$c}\" IS NOT NULL AND OLD.\"{$c}\" IS NOT NEW.\"{$c}\")",
            self::WRITE_ONCE,
        ));

        return [
            "CREATE TRIGGER contract_signatures_no_update BEFORE UPDATE ON contract_signatures
            WHEN NOT ({$immutable}) OR {$writeOnce}
            BEGIN SELECT RAISE(ABORT, 'contract_signatures: evidencia imutavel'); END;",
            "CREATE TRIGGER contract_signatures_no_delete BEFORE DELETE ON contract_signatures
            BEGIN SELECT RAISE(ABORT, 'contract_signatures: evidencia imutavel'); END;",
            "CREATE TRIGGER contract_evidence_events_no_update BEFORE UPDATE ON contract_evidence_events
            BEGIN SELECT RAISE(ABORT, 'contract_evidence_events: trilha append-only'); END;",
            "CREATE TRIGGER contract_evidence_events_no_delete BEFORE DELETE ON contract_evidence_events
            BEGIN SELECT RAISE(ABORT, 'contract_evidence_events: trilha append-only'); END;",
        ];
    }
}
