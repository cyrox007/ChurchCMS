# ChurchCMS implementation status

Last updated: 2026-09-26

## Current branch

`develop`

## Completed foundations

- PHP 8.3+ standalone runtime.
- Native autoloader.
- Router / Request / Response.
- PDO database manager.
- Native migration runner.
- Module manifests / registry / runtime providers.
- Theme manifests / inheritance / renderer.
- Dependency-free theme validator.
- Modernized legacy-inspired default theme.
- External API v1 foundation.
- Partner Bearer tokens stored by SHA-256 hash.
- API scopes, CORS allowlist and file-backed rate limiting.
- Generic syndication engine.
- Generic RSS 2.0 feed.
- Rambler/News feed adapter.
- Publication product/domain requirements recorded.

## Publications module — current implementation

Implemented:

- publication workflow statuses;
- publication types;
- publication table migration;
- stable public UUID;
- site key for future multi-site;
- slug/title/excerpt/rich body;
- HTML allowlist sanitizer;
- author;
- publication timestamps;
- explicit syndication targets;
- target-specific title/excerpt fields;
- internal write service:
  - create draft;
  - publish;
  - withdraw;
  - change syndication targets;
- public published-only repository queries;
- public list/detail site routes;
- default theme templates for list/detail;
- public JSON API;
- partner JSON API with `content.read`;
- incremental partner sync using `updated_since`;
- publication syndication provider;
- lazy DB access during module boot.

Not yet exposed:

- HTTP/admin write routes.

Reason: write endpoints are intentionally blocked until Auth + RBAC + CSRF + audit log exist.

## Administrator security — current implementation

Implemented:

- hardened PHP session cookie configuration;
- lazy session start;
- session id rotation on login;
- CSRF token service and middleware;
- baseline CSP/security headers;
- administrator login rate limiting;
- admin user table;
- roles and permissions tables;
- user-role and role-permission relations;
- role scope storage for future site/section restrictions;
- password_hash/password_verify login;
- automatic password rehash when PHP defaults change;
- protected /admin dashboard;
- CSRF-protected logout;
- first-superadmin CLI provisioning.

Not yet implemented:

- audit/security event log;
- permission management UI;
- role assignment UI;
- scope enforcement in individual domain modules;
- password reset flow;
- optional 2FA.

## Immediate next work

1. Validate current PHP 8.3 CI and fix issues.
2. Harden migrations and add database-backed smoke test.
3. Add audit/security event log.
4. Add permission guard for publication administration.
5. Build Publications admin/editor UI.
6. Add categories/tags/revisions/scheduling.
7. Build Media module and publication cover relation.

## Durable planning documents

- `docs/PRODUCT_REQUIREMENTS.md`
- `docs/ROADMAP.md`
- `docs/BACKLOG.md`
- `docs/DECISIONS.md`
- `docs/ARCHITECTURE.md`
- `docs/THEMES.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/API.md`
- `docs/SYNDICATION.md`

Update this file at the end of every substantial implementation increment.
