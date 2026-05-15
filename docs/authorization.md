# Enterprise Authorization Architecture

## 1. Capability Naming Convention

Capabilities are the only authorization primitive. Roles are only named bundles of capabilities.

Format:

```txt
domain.resource.action.scope
```

Examples:

```txt
core.dashboard.view.tenant
crm.customers.create.tenant
projects.projects.update.tenant
sales.quotations.export_pdf.tenant
security.users.delete.tenant
security.roles.assign_capabilities.tenant
```

Rules:

- Use lower snake case.
- Use verbs for actions: `view`, `create`, `update`, `delete`, `approve`, `export_pdf`, `assign_capabilities`.
- Scope is explicit: `tenant`, `own`, `assigned`, `global`.
- UI, controllers, services, API routes, and jobs must never check role names.
- Role codes are display/administration metadata only.

## 2. RBAC/Capability Schema

Core tables:

```txt
tenants
tenant_user
users
roles
capabilities
capability_role
role_user
```

Design:

- `capabilities` is the source of truth for authorization decisions.
- `roles` can be system roles or tenant-owned custom roles.
- `capability_role` maps capabilities into roles.
- `role_user` assigns roles to a user, optionally within a tenant.
- `tenant_user` supports future multi-tenant membership and active tenant selection.

Important columns:

```txt
capabilities
- id
- tenant_id nullable
- name
- code
- domain
- resource
- action
- scope
- is_system

roles
- id
- tenant_id nullable
- name
- code
- description
- is_system
- is_protected

role_user
- role_id
- user_id
- tenant_id nullable
```

## 3. Centralized Policy Layer

All authorization flows through `App\Auth\CapabilityAuthorizer`.

Allowed:

```php
$authorizer->allows($user, 'security.users.update.tenant', tenantId: $tenantId);
```

Not allowed:

```php
$user->hasRole('admin');
$user->roles->contains('owner');
in_array('admin', $roles);
```

The centralized layer is responsible for:

- collecting capabilities from assigned roles,
- tenant scoping,
- future custom role support,
- request-level decisions shared by UI and API,
- hiding role names from authorization logic.

## 4. Authorization Flow

```txt
Request
↓
auth middleware
↓
capability middleware / controller decorator
↓
CapabilityAuthorizer
↓
tenant-aware capability lookup
↓
Controller / Inertia / API response
```

The same middleware and authorizer are used by web UI and API routes.

## 5. CRUD Strategy

Admin CRUD should manage roles as capability bundles:

- Create role
- Edit role name/code/description
- Assign capabilities
- Assign role to users
- Prevent deletion of protected system roles

Capability CRUD should be restricted. Most capabilities are seeded by code; tenant custom roles should combine existing capabilities rather than create new executable authorization codes in the UI.
