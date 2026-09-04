---
name: cms-post-publisher
description: Publica artigos no CMS via infraestrutura AI Publisher. Usa discovery, schema dinâmico, guia editorial e cadastra posts como draft com validação e auditoria automáticas. Use ONLY when the task is to create or publish blog content through the CMS API.
---

# CMS Post Publisher

**Base URL**: `https://cms-api.noxtecnologias.com.br/api`

## Autenticação

Todas as chamadas à API requerem autenticação. O agente deve utilizar um **API Token**
(prefixo `api_`) ou **Bearer Token do Sanctum** no header:

```http
Authorization: Bearer <token>
```

O token deve ter as permissões:

- `ai.read` — para discovery, docs, schema, editorial guide
- `ai.publish` — para publicar posts
- `post.read` — para listar categorias existentes
- `post.create` — para criar categorias quando necessário

**Importante**: inclua sempre o header `Accept: application/json`. Sem ele, o Laravel retorna `500 Route [login] not defined` ao invés de `401 Unauthorized`, pois tenta redirecionar para uma rota de login inexistente.

---

# Campos aceitos pelo endpoint de publicação

O endpoint `POST /api/ai/posts` aceita **apenas** estes campos:

| Campo | Obrigatório | Auto-gerado se ausente | Limite |
|---|---|---|---|
| `title` | SIM | — | 255 chars |
| `content` | SIM | — | sem limite |
| `excerpt` | não | SIM (primeiros 400 chars do conteúdo) | 500 chars |
| `meta_title` | não | SIM (título truncado em 160 chars) | 160 chars |
| `meta_description` | não | SIM (excerpt truncado em 320 chars) | 320 chars |
| `featured_image` | não | — | URL, 2048 chars |
| `og_title` | não | — | 160 chars |
| `og_description` | não | — | 320 chars |
| `og_image` | não | — | URL, 2048 chars |
| `category` | não | — | nome ou slug da categoria |
| `tags` | não | — | array de strings |
| `source` | não | — | ex.: `hermes`, `claude`, `gpt` |
| `requested_by` | não | — | identificador do agente |

Campos **NÃO** aceitos: `slug` (auto-gerado), `status` (sempre draft), `schema_type`, `llm_summary`, `meta_keywords`, `category_id`, `featured_image_alt`.

---

# Regras Obrigatórias

- **Nunca** publicar sem revisão humana — todo conteúdo é criado como `draft`
- **Nunca** assumir campos obrigatórios — consultar sempre `GET /api/ai/schema/post`
- **Nunca** inventar slugs — o CMS gera automaticamente
- **Nunca** hardcodar categorias — listar via API
- Conteúdo mínimo: **200 caracteres** (texto puro, sem HTML)

---

# Fluxo Obrigatório

## 1. Descoberta

```http
GET /api/ai/discovery
```

Retorna recursos disponíveis e URLs dos endpoints.

## 2. Documentação

```http
GET /api/ai/docs
```

Retorna fluxo recomendado e dicas.

## 3. Guia editorial

```http
GET /api/ai/editorial-guide
```

Retorna tom de voz, audiência e regras de conteúdo da empresa.

## 4. Schema do post

```http
GET /api/ai/schema/post
```

Retorna campos obrigatórios, opcionais e status disponíveis. **Sempre consulte, nunca assuma.**

> ⚠️ **Atenção**: o schema retorna TODOS os campos do modelo Post (incluindo `status`, `schema_type`, `og_*`, `canonical_url`, etc.), mas o endpoint `POST /api/ai/posts` aceita apenas os campos listados na seção "Campos aceitos" acima. Campos fora dessa lista são **silenciosamente ignorados**. Confie na tabela de campos aceitos, não no schema cru.

## 5. Categorias existentes

```http
GET /api/categories
```

Se a categoria desejada não existir:

```http
POST /api/categories
```

```json
{ "name": "ERP" }
```

## 6. Criar post

```http
POST /api/ai/posts
```

Exemplo mínimo:

```json
{
  "title": "Quanto custa desenvolver um ERP em 2026",
  "content": "<h2>Introdução</h2><p>...</p><h2>Benefícios</h2><p>...</p><h2>Conclusão</h2><p>...</p>",
  "excerpt": "Descubra os fatores que influenciam o custo de desenvolvimento de um ERP sob medida.",
  "category": "ERP",
  "tags": ["ERP", "Desenvolvimento", "SaaS"],
  "source": "hermes"
}
```

O CMS irá **automaticamente**: gerar slug, estimar tempo de leitura, criar registro de auditoria e salvar como `draft`.

## 7. Sitemap

O sitemap dinâmico está disponível em:

```http
GET /api/sitemap.xml
```

Posts com `status = published` e `include_in_sitemap = true` aparecem automaticamente.

---

# Estrutura do artigo

- **Introdução**: contexto, problema, objetivo
- **Desenvolvimento**: usar H2, H3, listas, tabelas quando apropriado
- **Conclusão**: resumo, recomendação, CTA contextual

---

# SEO

- **meta_title**: até 160 caracteres (a API trunca se exceder)
- **meta_description**: até 320 caracteres (a API trunca se exceder)
- **slug**: gerado automaticamente do título pelo CMS

---

# Schema.org

O campo `schema_type` existe no banco (padrão `Article`) mas **não é aceito** pelo endpoint AI — é configurado manualmente via painel admin após revisão humana.

---

# Checklist antes de publicar

- [ ] Título preenchido
- [ ] Conteúdo com no mínimo 200 caracteres de texto puro
- [ ] Categoria definida (criar se necessário)
- [ ] excerpt preenchido (ou deixar o CMS auto-gerar)
- [ ] meta_title preenchido (ou deixar o CMS auto-gerar)
- [ ] meta_description preenchida (ou deixar o CMS auto-gerar)
- [ ] Post criado como draft — nunca publicado automaticamente

---

# Estratégia editorial

Priorizar conteúdos sobre:

ERP, CRM, SaaS, aplicativos mobile, Laravel, React, Next.js, integrações, WhatsApp, OpenAI, IA, automação, MVP, startups, escalabilidade.

Tipos de conteúdo: comerciais (custos), comparativos, educacionais.

Tom: profissional, claro, com exemplos práticos. Parágrafos de 4-5 linhas no máximo.
