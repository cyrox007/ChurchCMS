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

## Immediate next work

1. Validate PHP 8.3 CI and fix lint/runtime issues.
2. Harden migrations and add database-backed smoke test.
3. Core session/security headers/CSRF.
4. Administrator authentication.
5. RBAC and section-scoped permissions.
6. Audit log.
7. Publications admin/editor UI using the above security layer.
8. Publication categories/tags/revisions/scheduling.
9. Media module and publication cover relation.

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
