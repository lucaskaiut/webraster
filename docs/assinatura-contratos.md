# Assinatura de contratos — cadeia de evidências

## Resumo

A assinatura de contratos no aplicativo produz uma **cadeia de evidências verificável**:
documento congelado e selado, hash SHA-256 calculado no servidor sobre os bytes finais,
manifesto canônico assinado com Ed25519, trilha de eventos encadeada por hash e pacote
exportável que pode ser verificado sem acesso ao sistema.

A cadeia comprova **integridade** (o documento e as evidências não foram alterados depois do
registro) e o **contexto** do registro (conta autenticada, sessão, horário do servidor, IP
observado). Ela **não comprova, isoladamente, a identidade física** de quem desenhou a
assinatura, não garante validade jurídica universal e não torna a fraude impossível.

Módulo: `api/app/Modules/ContractEvidence`.

## Conceitos separados

| Conceito | O que é | Onde fica no manifesto |
|---|---|---|
| Conta autenticada | Usuário do app (token Sanctum) que concluiu a sessão. Definido pelo servidor. | `account` |
| Identidade declarada | Nome, CPF e nascimento digitados pelo signatário. Não verificados. | `signer.declared` |
| Verificação de apoio | Comparação do CPF declarado com o cadastro do cliente. | `signer.verified` |
| Contexto observado | IP visto pelo servidor, horário UTC do servidor, `request_id` gerado no servidor. | `context.observed` |
| Contexto declarado | User-Agent, `X-App-Version`, `X-App-Platform` enviados pelo app. | `context.declared` |
| Registro gráfico | Imagem da assinatura desenhada, vinculada à sessão e à versão do documento. | `artifacts.signature_image` |

## Fluxo

```text
App                                   API
 │ POST contract/portal/signing-sessions
 │──────────────────────────────────────▶ valida usuário ↔ cliente, contrato pendente
 │                                        grava HTML apresentado (privado) + SHA-256
 │                                        revoga sessões abertas do mesmo usuário
 │◀────────────────────────────────────── sessão {id, expires_at, document{version, sha256, html},
 │                                                 consent{version, text, sha256}}
 │ (leitura, dados, desenho, aceite explícito)
 │ POST .../signing-sessions/{id}/complete
 │   image, signer_*, consent_accepted, consent_sha256, document_sha256
 │──────────────────────────────────────▶ valida estado, expiração, token, versão, hashes
 │                                        open → completing (claim atômico)
 │                                        grava imagem + PDF selado (privado, write-once)
 │                                        transação: eventos, manifesto, assinatura,
 │                                        client_contract, sessão completed
 │                                        após commit: assina o manifesto (Ed25519)
 │◀────────────────────────────────────── {contract, signature, idempotent}
```

### Estados da sessão

```text
open → completing → completed
open → expired      (prazo, padrão 30 min)
open → revoked      (nova sessão, troca de contrato/versão)
completing → open   (falha de persistência ou recuperação pelo reconciliador)
```

### Regras

- O signatário é sempre a conta autenticada vinculada ao cliente; o app não escolhe cliente,
  contrato nem versão. Campos extras enviados pelo app são ignorados.
- Sessão expirada (`410 session_expired`) ou revogada (`410 session_revoked`) não conclui.
- Documento alterado após a abertura da sessão: `409 document_changed`.
- Conclusões concorrentes: só uma reivindica a sessão; as demais recebem `409 in_progress`.
- Reenvio idêntico após sucesso (mesma imagem e mesmo CPF) devolve o resultado original com
  `idempotent: true`; reenvio diferente: `409 already_completed`.
- Falha de persistência: arquivos descartados, sessão volta a `open`, evento `signing_failed`,
  resposta `503 persistence_failed`. O app nunca recebe sucesso falso.
- Contrato assinado é congelado: reatribuir o mesmo modelo não altera o corpo.
- Trocar o modelo ou o corpo de um contrato existente incrementa `document_version`, revoga
  sessões abertas e registra `document_version_changed`.
- Voltar para "pendente" ou trocar o contrato **supersede** a assinatura (`superseded_at`),
  sem apagar evidências.

## Endpoints

| Método | Rota | Permissão | Descrição |
|---|---|---|---|
| POST | `/contract/portal/signing-sessions` | `contract.sign` | Abre sessão (201) |
| POST | `/contract/portal/signing-sessions/{session}/complete` | `contract.sign` | Conclui |
| GET | `/contract/portal/document` | portal | PDF selado preservado |
| POST | `/clients/{client}/contract/signature` | `contract.sign` | Legado (ver Compatibilidade) |
| GET | `/clients/{client}/contract/evidence` | `client.read` | Assinaturas e eventos |
| GET | `/clients/{client}/contract/evidence/{signature}/verify` | `client.read` | Relatório |
| GET | `/clients/{client}/contract/evidence/{signature}/export` | `client.read` | Pacote `.zip` |

Erros de assinatura seguem `{success: false, message, code, errors}`; o app usa `code`.

## Tabelas

Migração `2026_10_14_000010_create_contract_evidence_tables` (reversível; preserva dados).

- `contract_signing_sessions` — sessão, snapshot apresentado, termo de consentimento, prazo.
- `contract_signatures` — registro imutável da assinatura e do manifesto. Só aceitam escrita
  única os campos de proteção (`manifest_signature`, `key_id`, `protected_at`) e de
  substituição (`superseded_at`, `superseded_reason`); `evidence_status` e `protection_error`
  evoluem. Sem exclusão.
- `contract_evidence_events` — trilha append-only encadeada por hash, `sequence` única por
  contrato do cliente.
- `client_contracts` — novas colunas `document_version` e `current_signature_id`.

As tabelas de evidência não têm chaves estrangeiras para contrato/cliente: a evidência
sobrevive à exclusão do cadastro.

### Proteção no banco (triggers)

Além do bloqueio nos models, triggers impedem `UPDATE` indevido e `DELETE` em
`contract_signatures` e `contract_evidence_events`:

```text
contract_signatures_no_update, contract_signatures_no_delete,
contract_evidence_events_no_update, contract_evidence_events_no_delete
```

No MySQL com binlog ativo, criar trigger exige `SUPER` (ou
`log_bin_trust_function_creators=1`). A migração tenta instalar e, se não conseguir, registra
um aviso sem falhar. Para instalar com um usuário privilegiado:

```bash
DB_USERNAME=root DB_PASSWORD=... php artisan contract-evidence:install-guards
```

O reconciliador avisa no log enquanto as triggers não estiverem instaladas.

## Armazenamento

Disco `CONTRACT_EVIDENCE_DISK` (padrão `local`, privado — não servido publicamente).

```text
contract-evidence/{tenant}/{client_contract_uuid}/sessions/{session}/presented.html
contract-evidence/{tenant}/{client_contract_uuid}/signatures/{signature}/signature.png
contract-evidence/{tenant}/{client_contract_uuid}/signatures/{signature}/signed.pdf
```

Escrita única: o arquivo não é sobrescrito e o hash é conferido após a gravação. O download
do contrato assinado serve sempre esses bytes (nunca regera o PDF). Arquivos legados no disco
`public` continuam sendo servidos como antes.

## Selo no PDF e validação pública

O PDF assinado recebe ao final apenas um selo compacto: QR Code, nome declarado, data e hora
(Brasília), código de verificação (UUID da assinatura) e a URL de validação. Os detalhes das
evidências ficam no manifesto e no painel, não no documento.

- URL: `CONTRACT_VERIFICATION_URL/{código}` (padrão `FRONTEND_URL/validar-contrato/{código}`).
- Página pública do painel (`/validar-contrato/:code`), sem login, consulta
  `GET /api/public/contract-signatures/{code}` (limite de 30 req/min) e mostra: resultado da
  verificação, se foi substituída, empresa, contrato, nome declarado, CPF mascarado, data,
  forma de assinatura, versão, hashes e chave. Não expõe IP, dispositivo, conta, CPF completo
  nem o conteúdo do contrato.
- A página permite conferir um PDF recebido: o SHA-256 é calculado no navegador e comparado
  com o hash registrado; o arquivo não é enviado ao servidor.
- O PDF não contém o próprio hash (ele só existe depois de gerado); o vínculo é feito pelo
  código de verificação.
- PDFs selados antes desta mudança permanecem como foram gerados (com a página de registro).

## Manifesto (formato `nuvemax.contract-evidence`, versão 1)

Serializado uma única vez em JSON canônico; os bytes gravados são os que foram assinados.

```json
{
  "format": "nuvemax.contract-evidence",
  "version": 1,
  "canonicalization": "nuvemax-cjson-v1",
  "hash_algorithm": "sha256",
  "signature_id": "uuid",
  "method": "app_signature",
  "signed_at": "2026-10-14T12:00:00.123456Z",
  "tenant": {"id": 1},
  "client": {"id": "uuid"},
  "contract": {"id": "uuid", "template_id": "uuid", "template_name": "...", "document_version": 2},
  "session": {"id": "uuid", "started_at": "...Z", "expires_at": "...Z"},
  "account": {"user_id": "uuid", "authentication": "sanctum_personal_access_token", "token_id": 10},
  "signer": {
    "declared": {"name": "...", "cpf_masked": "***.456.789-**", "cpf_commitment": "hex", "birth_date_commitment": "hex"},
    "commitment_scheme": "sha256(\"<signature_id>:<valor>\")",
    "verified": {"cpf_matches_client_record": true}
  },
  "consent": {"explicit": true, "version": "2026-10-14.1", "text": "...", "sha256": "hex", "accepted_at": "...Z"},
  "artifacts": {
    "presented_document": {"media_type": "text/html", "sha256": "hex"},
    "signed_document": {"media_type": "application/pdf", "sha256": "hex", "size": 12345},
    "signature_image": {"media_type": "image/png", "sha256": "hex"}
  },
  "context": {
    "request_id": "uuid",
    "observed": {"ip": "203.0.113.10", "received_at": "...Z"},
    "declared": {"user_agent": "...", "app_version": "1.4.0", "app_platform": "android"}
  },
  "events": {"head_sequence": 4, "head_hash": "hex", "items": [{"sequence": 1, "type": "...", "occurred_at": "...Z", "hash": "hex"}]}
}
```

Métodos (`method`): `app_signature`, `legacy_app_signature`, `uploaded_document` (arquivo
importado pela equipe) e `manual_attestation` (marcação manual pela equipe). Os dois últimos
não contêm assinatura do cliente e são exibidos como tal.

### Canonicalização `nuvemax-cjson-v1`

- Chaves de objetos ordenadas por byte, recursivamente; listas mantêm a ordem.
- UTF-8, sem escapes de unicode ou barras, sem espaços.
- Apenas string, inteiro, booleano e `null` (ponto flutuante é rejeitado).
- Datas em UTC: `Y-m-d\TH:i:s.u\Z` (microssegundos).

### Hash dos eventos

```text
hash = sha256(cjson({
  v: 1, uuid, tenant_id, client_contract_id, client_contract_uuid, sequence, type, result,
  occurred_at, session_id, signature_id, user_id, request_id, ip, user_agent, app_version,
  data (ou null), previous_hash
}))
```

`previous_hash` é o `hash` do evento anterior do mesmo contrato (`null` no primeiro). O
manifesto inclui a cabeça da cadeia no momento da assinatura; eventos posteriores (proteção,
exportação, substituição) continuam a cadeia.

## Chaves

- Algoritmo: Ed25519 (libsodium).
- `key_id` = `ed25519-` + 16 primeiros hex de `sha256(chave_pública)`.
- Geração (só imprime; nada é gravado):

  ```bash
  php artisan contract-evidence:generate-key
  ```

- Configuração (cofre de segredos do ambiente; nunca versionar):

  | Variável | Uso |
  |---|---|
  | `CONTRACT_EVIDENCE_KEY_ID` | Chave ativa |
  | `CONTRACT_EVIDENCE_PRIVATE_KEY` | Segredo base64 (32 ou 64 bytes) |
  | `CONTRACT_EVIDENCE_PRIVATE_KEY_PATH` | Alternativa: arquivo com o segredo |
  | `CONTRACT_EVIDENCE_TRUSTED_KEYS` | Públicas aceitas: `kid:base64,kid2:base64` |
  | `CONTRACT_EVIDENCE_DISK` | Disco privado das evidências |
  | `CONTRACT_SIGNING_SESSION_TTL` | Prazo da sessão (min) |
  | `CONTRACT_VERIFICATION_URL` | Base da URL impressa no QR Code |
  | `CONTRACT_ALLOW_LEGACY_SIGNING` | Aceita o endpoint legado |
  | `TRUSTED_PROXIES` | Proxies confiáveis para o IP real |

- **Rotação**: gere um novo par, troque a chave ativa e **mantenha a pública antiga** em
  `CONTRACT_EVIDENCE_TRUSTED_KEYS`. Manifestos antigos continuam verificáveis; os novos usam a
  nova chave. Remover uma pública torna as verificações dos manifestos dela `inconclusive`.
- Sem chave configurada, a assinatura é concluída e a evidência fica `pending`; o
  reconciliador assina o manifesto quando a chave existir (o manifesto não muda).

## Reconciliação

`contract-evidence:reconcile` (agendado a cada 5 min, sem sobreposição):

- expira sessões vencidas;
- recupera sessões presas em `completing` há mais de 5 min;
- protege manifestos pendentes;
- avisa no log se faltar chave ou triggers.

## Verificação

```bash
php artisan contract-evidence:verify {signature_uuid} [--json]
php artisan contract-evidence:verify --package=evidencias.zip [--json]
```

| Resultado | Saída | Significado |
|---|---|---|
| `valid` | 0 | Todas as verificações passaram |
| `integrity_failure` | 1 | Hash, assinatura ou cadeia não conferem |
| `missing` | 2 | Artefato ou registro ausente |
| `inconclusive` | 3 | Ex.: manifesto ainda não assinado ou chave não confiável |

Verificações: `manifest_hash`, `manifest_format`, `manifest_canonical`, `manifest_signature`,
`artifact:presented_document`, `artifact:signed_document`, `artifact:signature_image`,
`events_chain`, `events_binding`, `events_completion`, `events_sequence`, `record_binding`.

Na verificação de pacote, a chave pública embutida no `.zip` sozinha resulta em
`inconclusive`: a confiança vem da lista configurada (ou da publicação da chave pela empresa).

### Pacote exportado

```text
manifest.json        bytes exatos do manifesto
manifest.sig         assinatura Ed25519 (binária)
manifest.sig.json    algoritmo, key_id, hash, data e assinatura em base64
public-key.pem       chave pública (SPKI)
events.json          trilha completa do contrato
artifacts/           documento apresentado, PDF assinado, imagem da assinatura
verification.json    relatório no momento da exportação
LEIA-ME.txt          instruções
```

Verificação independente com OpenSSL 3:

```bash
sha256sum manifest.json artifacts/*
openssl pkeyutl -verify -pubin -inkey public-key.pem -rawin \
  -in manifest.json -sigfile manifest.sig
```

Compare os hashes com `artifacts.*.sha256` do manifesto e confira o `key_id` com a chave
pública publicada pela empresa.

## Compatibilidade

- Contratos assinados antes desta versão ficam com `evidence_level = legacy`: nenhuma
  evidência é criada retroativamente e os dados existentes não são alterados.
- O endpoint legado `POST /clients/{client}/contract/signature` continua aceito enquanto
  `CONTRACT_ALLOW_LEGACY_SIGNING=true`, gerando `legacy_app_signature` (sem consentimento
  explícito nem vínculo com a versão lida). Com `false`, responde `426 app_update_required`.
- Importação de contrato e marcação manual pela equipe passam a gerar registros
  `uploaded_document` / `manual_attestation`, distintos da assinatura do cliente.

## LGPD

- CPF completo não vai para logs, trilha, manifesto nem auditoria; o manifesto guarda o CPF
  mascarado e um compromisso (`sha256("<signature_id>:<cpf>")`).
- O compromisso é pseudonimização, não anonimização: o espaço de CPFs é pequeno e permite
  força bruta a quem tiver o `signature_id`.
- Tokens, senhas e chaves não são gravados; do token, só o identificador numérico.

## Limitações e riscos residuais

- O desenho da assinatura é um registro gráfico; não comprova identidade física.
- A identidade declarada não é validada em base oficial; não há segundo fator na assinatura.
- O horário é do servidor; não há carimbo de tempo de terceira parte (RFC 3161).
- A imutabilidade no banco depende das triggers; um administrador do banco ainda pode
  removê-las — por isso a cadeia de hashes e o manifesto assinado são a defesa principal.
- A chave privada está no servidor de aplicação; o comprometimento do servidor permite
  assinar manifestos novos (não alterar os já exportados).
- Assinaturas legadas têm evidência mais fraca.
