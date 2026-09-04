# Template Governance — nox-skeleton

> This is the **Origin Template** — a **generic multi-tenant SaaS starter**.
> It is NOT a CMS, ERP, or eCommerce. It contains only infrastructure that any
> domain-specific application can be built upon.

---

## What the Template Owns (CORE)

### Backend

- Multi-tenancy, ACL/RBAC, Authentication, User/Token management
- Shared utilities, File upload, Middleware, Error handling
- Generic webhooks (CRUD + dispatch infrastructure)
- **Billing / Subscriptions** — SaaS monetization: plans, subscriptions, invoices, payment gateways (e.g. Asaas). This is subscription billing for the product itself, **not** ERP/fiscal invoices.
- Database structure for core entities

### Frontend

- Design System components
- HTTP layer, State stores, Shared types/constants/hooks/utils
- Guards (Auth, Guest, Permission), Layouts (AppLayout, AuthLayout)
- Router structure, Providers, CSS design tokens
- Auth, Dashboard, Users, Roles, API Tokens, Webhooks modules
- Billing / checkout / payment-pending flows (SaaS subscription monetization)

### Infrastructure

- Docker Compose (mysql, php-fpm, nginx, node/vite)
- Dockerfiles for PHP, Nginx, MySQL, Web (Node/Vite)
- Environment templates

---

## What the Template does NOT own

- Blog / Posts / Categories / Tags
- eCommerce (Products, Orders, Cart)
- ERP (Invoices, Inventory, Suppliers) — ERP/fiscal invoices remain forbidden; SaaS subscription invoices above are CORE
- CRM (Leads, Deals, Pipelines)
- AI content publisher
- **Any domain-specific entity or workflow**

---

## Key Rules

- **Permission enum** contains only infrastructure permissions (user.*, tenant.*, role.*, api-token.*, webhook.*, plan.*, subscription.*, invoice.*). Domain permissions are defined in derived projects.
- **Design System components** are always CORE — UI primitives, not domain logic.
- **Decision test:** "Would an ERP, eCommerce, or CRM need this?" No → not CORE. Exception: generic SaaS subscription billing is CORE because every paid SaaS needs monetization.

---

## For Derived Projects

Projects live alongside this template as sibling directories:

```
development/
├── nox-skeleton/       ← This template (CORE only)
└── nox-cms/            ← Derived project (CORE + PROJECT)
```

There is no git remote coupling. CORE changes are synchronized by copying
files between directories — see the project's `.opencode/skills/template-governance/SKILL.md`
for the detailed workflow.
