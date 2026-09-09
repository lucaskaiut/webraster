---
name: template-governance
description: Use when the user asks about governance, template rules, or before implementing changes. This skill establishes mandatory rules for what belongs in the template versus derived projects.
---

# Template Governance — Web Raster

## Context

This repository is the **Origin Template** — a **generic multi-tenant SaaS starter**.
It is NOT a CMS, ERP, or eCommerce. It is a foundation that any domain-specific
application can be built upon.

Derived projects live as sibling directories:

```
development/
├── web-raster/         ← This template (CORE only)
└── web-raster-app/     ← Derived project (CORE + PROJECT)
```

There is no git remote coupling. Each repo has its own `origin`.

---

## Core Principle

> Template = Generic Infrastructure. Project = Domain Logic.

This repository must **only** contain code that any SaaS would need.
Domain-specific code (posts, products, ERP invoices, AI publishers, etc.)
is **forbidden** here — it belongs in derived projects.

**Exception (CORE):** SaaS subscription billing (plans, subscriptions, invoices,
payment gateways) is generic monetization infrastructure — not ERP/fiscal invoicing.

---

## What the Template Owns (CORE)

### Backend (`api/`)

| Concern | Path |
|---|---|
| Multi-tenancy (scope, context, resolver, strategies) | `api/app/Modules/Tenant/` |
| ACL / RBAC (permission enum, roles, HasRoles trait, middleware) | `api/app/Modules/ACL/` |
| Authentication (Sanctum + session SPA, register, login, logout) | `api/app/Modules/Auth/` |
| User management (tenant-scoped CRUD, soft deletes) | `api/app/Modules/User/` |
| API token management (scoped tokens, hashed storage) | `api/app/Modules/ApiToken/` |
| Shared utilities (ApiController, HasUuid, Document validators, ApiError) | `api/app/Modules/Shared/` |
| File upload (generic endpoint) | `api/app/Modules/Shared/Http/Controllers/FileUploadController.php` |
| Middleware (tenant, permission, api-token auth, subscription) | `api/app/Modules/*/Http/Middleware/` |
| Generic webhooks (CRUD, dispatch, send jobs) | `api/app/Modules/Webhook/` |
| Billing / Subscriptions (plans, subscriptions, SaaS invoices, gateways) | `api/app/Modules/Billing/` |
| Error handling / standardized JSON responses | `api/bootstrap/app.php` |
| Database structure (tenants, users, ACL, api_tokens, sessions, jobs, webhooks, billing) | `api/database/migrations/` |
| Factories and seeders for core entities | `api/database/factories/`, `api/database/seeders/` |
| Route conventions for core modules | `api/routes/api.php` |

### Frontend (`web/`)

| Concern | Path |
|---|---|
| Design System (Button, Card, DataTable, Input, Form, Modal, Sidebar, Topbar, ImageUploader, RichTextEditor, SlugField, TagSelector, SeoCard, Calendar, DatePicker, DateRangeFilter, RangeCalendar, SearchSelect, etc.) | `web/src/shared/design-system/` |
| HTTP layer (Axios, CSRF, error interceptor) | `web/src/shared/api/` |
| State stores (session, theme, toast, UI) | `web/src/shared/stores/` |
| Shared types/constants (generic API types, query keys) | `web/src/shared/types/`, `web/src/shared/constants/` |
| Shared hooks and utilities (debounce, permissions, CN, format, document, date, date-range) | `web/src/shared/hooks/`, `web/src/shared/utils/` |
| Guards (Auth, Guest, Permission) | `web/src/app/guards/` |
| Layouts (AppLayout, AuthLayout) | `web/src/app/layouts/` |
| Router structure | `web/src/app/router/` |
| Providers (Query, Theme, Session) | `web/src/app/providers/` |
| CSS design tokens / theme variables | `web/src/index.css` |
| Auth module (login, register, session, checkout/payment pending) | `web/src/modules/auth/` |
| Billing module (plans, subscription, invoices UI) | `web/src/modules/billing/` |
| Dashboard module (basic stats) | `web/src/modules/dashboard/` |
| Users module (generic CRUD) | `web/src/modules/users/` |
| Roles module (generic CRUD + permission checkboxes) | `web/src/modules/roles/` |
| API Tokens module (generic CRUD) | `web/src/modules/api-tokens/` |
| Webhooks module (generic CRUD) | `web/src/modules/webhooks/` |

### Infrastructure

| Concern | Path |
|---|---|
| Docker Compose (mysql, php-fpm, nginx, node/vite) | `docker-compose.yml` |
| PHP container (PHP 8.4 + extensions + Composer + startup script) | `docker/php/` |
| Nginx configuration (reverse proxy, security headers) | `docker/nginx/` |
| MySQL configuration (utf8mb4) | `docker/mysql/` |
| Web container (Node 22 + Vite startup script) | `docker/web/` |
| Environment templates | `api/.env.docker`, `web/.env.docker` |

### What is FORBIDDEN here

- Blog / Posts / Categories / Tags
- eCommerce (Products, Orders, Cart)
- ERP (Invoices, Inventory, Suppliers) — ERP/fiscal invoices remain forbidden; SaaS subscription billing invoices are CORE
- CRM (Leads, Deals, Pipelines)
- AI content publisher
- **Any domain-specific entity or workflow**

These belong in derived projects, never in this template.

---

## Permission Enum — The Boundary

The `Permission` enum (`api/app/Modules/ACL/Enums/Permission.php`) contains
**only infrastructure-level permissions**:

- `user.create`, `user.read`, `user.update`, `user.delete`
- `tenant.read`, `tenant.update`
- `role.create`, `role.read`, `role.update`, `role.delete`
- `api-token.create`, `api-token.read`, `api-token.delete`
- `webhook.create`, `webhook.read`, `webhook.update`, `webhook.delete`
- `plan.create`, `plan.read`, `plan.update`, `plan.delete`
- `subscription.read`, `subscription.update`
- `invoice.read`

Domain permissions (e.g., `post.create`, `product.read`) must NOT be added here.

---

## Design System — The Exception

Design System components are **always CORE**, regardless of what prompted their
creation. If an `ImageUploader` was built for a blog project, it stays here
because any SaaS might need image uploads. Same for `RichTextEditor`,
`SlugField`, `TagSelector`, `SeoCard`, `DatePicker`, `DateRangeFilter`,
`SearchSelect` — they are UI primitives, not domain logic.

---

## Decision Questions

1. **Would an ERP, eCommerce, or CRM need this?** If NO → forbidden here.
2. **Is this a UI primitive or infrastructure concern?** If YES → allowed here.
3. **Is this tied to a specific business domain?** If YES → forbidden here.
4. **Would this exist in every SaaS regardless of niche?** If YES → allowed here.
5. **Is this SaaS subscription monetization (plans/subscriptions/payment gateways)?** If YES → CORE (not ERP invoices).

---

## How Changes Arrive Here

CORE changes are first implemented and tested in a derived project, then copied
here via file sync (rsync/cp). Paths are identical between repos.

When receiving CORE changes:

1. Review the incoming files — ensure no domain code leaked in.
2. Verify the template still works standalone: `docker compose up`.
3. Commit with a clear message.

Never pull domain code in. Never add project-specific routes, permissions, or
modules.

---

## Priority

This governance document has priority over implicit project conventions.
All agents, developers, and automations MUST consult and follow these
guidelines before adding or removing any code in this repository.
