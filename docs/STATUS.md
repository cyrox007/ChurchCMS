# ChurchCMS implementation status

Last updated: 2026-09-26

## Current branch

`develop`

## Runtime baseline

Implemented:

- PHP 8.3+ standalone runtime;
- no mandatory Composer/framework/npm dependency;
- native autoloader;
- router/request/response;
- PDO PostgreSQL/MySQL infrastructure;
- migration runner;\n- database-free migration CLI validation (`php bin/migrate.php validate`);
- module manifests/runtime providers/capabilities;
- theme manifests and inheritance;
- PHP 8.3 lint + migration validation + theme validation CI.

## Installation

Implemented:

- four-step browser installer;
- environment/extension/writeability checks;
- automatic URL defaults;
- site profile selection;
- PostgreSQL/MySQL setup;
- automatic DB creation where credentials allow it;
- automatic migrations;
- first superadmin creation;
- atomic local configuration;
- generated 256-bit secret key;
- completed-installer lock;
- interrupted-install recovery before completion.

Pending:

- final installation healthcheck;
- update/backup wizard.

## Security / administration

Implemented:

- hardened session cookies;
- CSRF for authenticated browser mutations;
- stateless HMAC token for anonymous public forms;
- security headers/CSP;
- login throttling;
- admin authentication with password_hash/password_verify;
- automatic password rehash;
- RBAC schema/services;
- scope storage;
- audit event foundation;
- protected admin dashboard;
- friendly publication editor;
- comment moderation queue.

Pending:

- unified Admin Shell replacing the current dashboard-card prototype;
- common admin navigation/header/search/notifications;
- shared admin states and responsive shell;
- roles/users management UI;
- password reset;
- optional 2FA;
- production error handler;
- scoped permission enforcement in future domain modules.

## Publications

Implemented:

- types and workflow statuses;
- DB schema and public UUID;
- site key;
- automatic Russian-friendly slug;
- title/excerpt/body/author;
- comments_enabled per publication;
- explicit syndication targets;
- internal create/update/publish/withdraw service;
- public list/detail pages;
- admin list/editor;
- public API;
- partner API with incremental sync;
- RSS/Rambler syndication provider;
- audit/cache invalidation on changes.

Next:

- categories/tags;
- revisions;
- scheduled publication execution;
- Media/cover relation.

## Comments

Implemented:

- optional module;
- per-publication on/off switch;
- approved public comments;
- premoderated submissions by default;
- plain-text input;
- stateless public form token;
- rate limiting;
- approve/reject/spam moderation;
- audit events.

## SEO / sharing

Implemented:

- SEO module;
- per-publication SEO storage;
- automatic SEO defaults from publication;
- optional SEO title/description/keywords;
- canonical override;
- index/follow controls;
- Open Graph;
- Twitter/X card tags;
- social title/description/image overrides;
- Article/NewsArticle microdata;
- author/published/modified semantic metadata;
- dynamic robots.txt;
- dynamic XML sitemap;
- Sitemap directive;
- configurable share providers;
- Telegram/VK/OK share links;
- native Web Share button;
- copy-link action;
- no third-party share SDK/runtime dependency.

Next:

- Media image dimensions/variants in Article markup;
- video structured metadata when Video/Media module lands;
- organization/site-level SEO settings UI;
- redirect manager.

## Performance

Implemented:

- anonymous pages do not start PHP sessions;
- dependency-free full-page cache;
- version-based O(1) cache invalidation;
- public max-age + stale-while-revalidate;
- versioned immutable theme assets;
- query/API result bounds;
- third-party channel sync isolated from public requests.

Pending before 1.0:

- database-backed smoke environment in CI;
- large fixture dataset;
- HTTP load tests;
- query-plan/index audit;
- reference hardware thresholds;
- Nginx/reverse-proxy deployment profile.

## External channels / social / video

Architecture implemented:

- open provider IDs: no fixed provider whitelist in storage;
- adapter capability vocabulary;
- generic inbound/outbound DTOs;
- encrypted credential storage foundation;
- connection schema;
- publication outbox schema;
- inbound external-content inbox;
- remote ID/fingerprint deduplication;
- sync cursor/error state;
- polling synchronization service;
- runtime capability.

The intended model is:

```
official ChurchCMS publication
        |
        +--> selected external channels (outbox)
        |
        +--> canonical official URL

external network/video content
        |
        +--> inbox/review
        |
        +--> explicit import/link by operator
```

External content never becomes official automatically by default.

Next:

- connection/admin UI;
- outbound worker;
- inbound review UI;
- Telegram/VK/MAX adapters;
- YouTube/Rutube adapters;
- webhook contract.

## Administration UX direction

The current `/admin`, publication editor and comment moderation screens are functional prototypes.

They are not considered the final ChurchCMS administration interface.

Next administration milestone:

- build one shared Admin Shell;
- move existing Publications and Comments screens into it;
- add persistent/collapsible navigation;
- add site/user context header;
- add pending-work notifications;
- reserve a common global-search surface;
- expose future Media, People, Worship, Events, External Channels, Users/Roles, Themes, Settings, Backups and System Health through the same shell;
- preserve the requirement that routine operator work takes minimal actions and avoids technical terminology.

## Durable project memory

- `docs/PRODUCT_REQUIREMENTS.md`
- `docs/ROADMAP.md`
- `docs/BACKLOG.md`
- `docs/DECISIONS.md`
- `docs/STATUS.md`
- `docs/ARCHITECTURE.md`
- `docs/THEMES.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/API.md`
- `docs/SYNDICATION.md`
- `docs/COMMENTS.md`
- `docs/INSTALLATION.md`

Update this file at the end of every substantial implementation increment.
