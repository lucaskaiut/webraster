# Regras do Projeto

## Design System (frontend)

Regras obrigatórias para qualquer componente visual em `web/`:

- **Bordas não são aceitas** para delimitar ou indicar cards, painéis, modais, dropdowns e caixas (não use `border-*` ou `ring-*` com essa finalidade).
- Use **contraste de superfície** (`bg-surface`, `bg-surface-2`, `bg-surface-3`) combinado com **sombra** (`shadow-card`, `shadow-pop`) para indicar hierarquia e separação. Referência: `web/src/shared/design-system/Card.tsx`.
- Prefira os componentes do design system (`Card`, `Modal`, `Dropdown`, ...) a recriar superfícies com estilos próprios.

## Mensagens de Commit

Ao criar um commit, siga estas regras obrigatórias:

- Sempre em **inglês**.
- Toda **minúscula** (lowercase).
- No **presente do indicativo** na **terceira pessoa do singular**.

Exemplos válidos:

- `adds login form`
- `fixes invoice total calculation`
- `updates user permissions`

Exemplos inválidos:

- `Add login form` (não é minúscula)
- `added login form` (passado)
- `add login form` (não é terceira pessoa)
