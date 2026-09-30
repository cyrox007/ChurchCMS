# Administrator security

ChurchCMS administrative write operations are protected by a dedicated administrator authentication and authorization layer.

## Principles

- public-site users and CMS administrators are separate concerns;
- no plaintext passwords;
- no password stored in session/cookie;
- session id rotates after successful login;
- state-changing browser requests require CSRF validation;
- login attempts are rate limited;
- inactive administrators are rejected even when a session exists;
- authorization is permission-based, with roles as permission bundles;
- superadmin is an explicit role;
- site/section scope storage exists for future scoped editors.

## Database model

```
admin_users
roles
permissions
admin_user_roles
role_permissions
admin_role_scopes
```

`admin_role_scopes` is designed for cases such as:

- editor may edit only Sunday School;
- editor may edit only one site in a multi-site installation;
- education editor may edit only admissions.

Scope enforcement belongs to each domain action and will be added as administrative modules are exposed.

## Passwords

Passwords are stored with:

```php
password_hash($password, PASSWORD_DEFAULT)
```

and verified with:

```php
password_verify(...)
```

On successful login ChurchCMS checks `password_needs_rehash()` and upgrades the stored hash when appropriate.

## Sessions

Session configuration uses:

- strict mode;
- cookies only;
- HttpOnly;
- SameSite=Lax by default;
- Secure automatically on HTTPS;
- configurable lifetime;
- session id regeneration at login.

No credential is stored in the session.

## CSRF

Browser state mutations use `CsrfMiddleware`.

Token sources:

- form field `csrf_token`;
- `X-CSRF-Token`;
- `X-XSRF-Token`.

Templates generate hidden fields with:

```php
<?= $theme->csrfInput() ?>
```

## Security headers

The global middleware applies a baseline including:

- Content-Security-Policy;
- X-Content-Type-Options;
- X-Frame-Options;
- Referrer-Policy;
- Permissions-Policy;
- Cross-Origin-Opener-Policy;
- HSTS on HTTPS.

## Creating the first superadmin

Run migrations first:

```bash
php bin/migrate.php
```

Then set the password via environment rather than a command-line argument:

```bash
CHURCHCMS_ADMIN_PASSWORD='a-long-unique-password' \
php bin/create-admin.php admin "Administrator"
```

Minimum bootstrap password length is 12 characters. Production policy/UI will become stricter before release.

## Routes

```
GET  /admin/login
POST /admin/login
GET  /admin
POST /admin/logout
```

Admin write routes for domain modules must additionally check explicit permissions before performing mutations.

## Current roles

The initial migration creates:

- superadmin;
- administrator;
- editor;
- author;
- media_manager.

The migration also creates core permission keys for administration, publications, media, users and settings.

A normal role receives permissions through `role_permissions`. Superadmin is treated as unrestricted by `AuthorizationService`.

## Восстановление пароля

Forgotten-password flow использует одноразовые 256-битные токены. В БД
хранится только SHA-256, срок жизни и отметка использования. Новый запрос
инвалидирует предыдущий токен, успешный reset повышает `auth_version` и
отзывает старые admin-сессии.

Ответ формы запроса не раскрывает наличие email. Запрос и применение reset
защищены CSRF и отдельным rate-limit.

Доставка по умолчанию выключена. Встроенный native mail transport включается
только через локальную конфигурацию. Ошибка доставки инвалидирует созданный
токен; plaintext token не пишется в журнал.

## Незакрытые security-задачи

Перед релизом ещё стоит отдельно решить:

- UI управления ролями/permissions;
- review idle/absolute session timeout;
- optional/policy-driven 2FA.
