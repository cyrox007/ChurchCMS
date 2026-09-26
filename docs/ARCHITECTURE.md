# ChurchCMS architecture

## Baseline

ChurchCMS targets PHP 8.3+ and intentionally has no mandatory third-party runtime dependencies.

The core architecture follows the proven standalone patterns used in the Notes project while removing Notes-specific workspace, licensing and application-domain concerns.

## Layers

```
index.php
  -> core.php
     -> RuntimeAutoloader
     -> Config
     -> ModuleRegistry
     -> ModuleRuntimeLoader
     -> routes
     -> Router
        -> middleware
        -> controller
        -> services/models
        -> DatabaseManager (PDO)
```

## Directory model

```
app/
  controllers/
  middlewares/
  models/
  services/

core/
  runtime infrastructure

modules/
  <module>/
    module.json
    runtime.php
    controllers/
    services/
    views/
    assets/

config/
  app.php
  local.php (untracked)
  routes.php

database/
  migrations/

storage/
  cache/
  logs/
  sessions/
  uploads/
```

## Dependency policy

Runtime code must use PHP standard functionality and required PHP extensions only.

No Composer/vendor dependency is required to boot the application.

## Database

PDO is the only database access primitive. Emulated prepares are disabled. Application queries must use prepared statements for values.

Primary database target: PostgreSQL.

Compatibility target: MySQL 8 where practical.

## Modules

A module is discovered through `module.json`.

A module may expose a runtime provider. Runtime entrypoints are resolved with realpath checks and may not escape their module directory.

Profiles such as Parish, Cathedral and Education are compositions of modules, not forks of the CMS.

## Security defaults

- PHP 8.3 minimum.
- Front controller routing.
- No passwords or credentials in source control.
- Prepared SQL.
- Output escaping at rendering boundary.
- CSRF middleware for state-changing browser requests.
- Session hardening.
- Explicit upload allowlists.
- Module path traversal protection.
- No executable user uploads.
