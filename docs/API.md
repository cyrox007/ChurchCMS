# External API

ChurchCMS exposes data to trusted external systems through a versioned HTTP API.

Typical consumers:

- diocesan websites;
- parish networks;
- mobile applications;
- public information screens;
- approved integration services.

The API is not database access. External consumers receive explicit read-only projections.

## Base URL and versioning

Current contract:

```
/api/v1/...
```

Breaking response-contract changes require a new major API namespace such as `/api/v2`.

Existing v1 endpoints must not silently change their field meaning.

## Two access modes

### Public API

For information that is already public on the ChurchCMS website.

Examples planned for content modules:

```
GET /api/v1/publications
GET /api/v1/publications/{slug}
GET /api/v1/events
GET /api/v1/worship-services
GET /api/v1/people
```

Public endpoints may return only content that is currently published and externally visible.

Drafts, moderation state, private notes, account information, internal metadata and unpublished media must never appear.

### Partner API

For trusted systems such as a diocesan website.

Authentication:

```
Authorization: Bearer <token>
```

Partner tokens have explicit scopes, for example:

```
content.read
events.read
worship.read
people.read
media.read
```

An endpoint requiring a scope uses:

```php
ApiAccess::requireScope($request, 'content.read');
```

A token without that scope receives HTTP 403.

## Creating a partner token

Generate a cryptographically random token:

```bash
php bin/api-token.php diocese-site
```

The command prints:

- the plaintext token once;
- its SHA-256 hash.

Give the plaintext token to the integration owner over an appropriate secure channel.

ChurchCMS stores only the SHA-256 hash in `config/local.php`.

Example:

```php
'api' => [
    'partner_tokens' => [
        'diocese-site' => [
            'hash' => '<64 hex characters>',
            'scopes' => [
                'content.read',
                'events.read',
                'worship.read',
            ],
            'origins' => [
                'https://example-diocese.ru',
            ],
            'enabled' => true,
        ],
    ],
],
```

Never commit `config/local.php`.

## Token properties

API tokens:

- are generated from 256 bits of random data;
- are sent as Bearer credentials;
- are never stored in plaintext by ChurchCMS configuration;
- can be disabled independently;
- can receive different scopes;
- may optionally be bound to allowed browser origins.

Because tokens contain high entropy, SHA-256 hashes are suitable for token lookup/verification. User passwords use a password hashing algorithm instead and are a separate mechanism.

## Data minimization

Database records and domain models must never be serialized directly.

Every externally exposed entity must have an explicit API projection implementing:

```php
ChurchCMS\Core\ApiResource
```

For example a publication projection may expose:

```json
{
  "id": "public-stable-id",
  "slug": "...",
  "title": "...",
  "excerpt": "...",
  "organization_owner_id": "stable-organization-public-id",
  "published_at": "...",
  "url": "...",
  "cover": {}
}
```

and intentionally omit:

- moderation notes;
- editor IDs;
- draft body revisions;
- internal flags;
- unpublished attachments;
- author email addresses;
- storage paths;
- database implementation details.

The API contract decides what leaves the system, not the table schema.

## Stable identifiers

External systems must not depend on database primary keys.

Public objects will receive stable external identifiers or canonical slugs. This allows ChurchCMS to migrate or reorganize its database without breaking diocesan integrations.

Публикации передают `organization_owner_id` как stable public ID локальной
organization unit. Агрегаторы должны сохранять его вместе с материалом и не
подменять владельца сайтом, который только ретранслировал публикацию.

## Pagination

Collection endpoints will use bounded pagination.

Planned convention:

```
?page=1&per_page=20
```

`per_page` will have a server-side maximum.

Responses include pagination in `meta`, rather than returning an unbounded dataset.

## Incremental synchronization

For diocesan aggregation, content APIs should support incremental synchronization instead of requiring full downloads.

Planned filters:

```
?updated_since=2026-09-26T00:00:00Z
?published_since=2026-09-01T00:00:00Z
```

The contract will use UTC ISO-8601 timestamps.

A later phase may add cursor-based sync for large installations.

## Deletions

Consumers also need to know when syndicated content was withdrawn.

The API design therefore reserves a deletion/tombstone mechanism so an external site can remove a previously synchronized publication instead of keeping stale copies forever.

## Response envelope

Successful response:

```json
{
  "api_version": "v1",
  "data": {},
  "meta": {
    "request_id": "..."
  }
}
```

Error:

```json
{
  "api_version": "v1",
  "error": {
    "code": "invalid_api_token",
    "message": "...",
    "details": {}
  },
  "meta": {
    "request_id": "..."
  }
}
```

Every API response receives:

```
X-ChurchCMS-API-Version
X-Request-ID
X-Content-Type-Options: nosniff
```

## Rate limits

Default configuration:

- public API: 120 requests/minute per remote IP and endpoint;
- partner API: 600 requests/minute per partner and endpoint.

The initial standalone limiter uses locked local files under `storage/rate-limits/`, so no Redis dependency is required.

Responses expose:

```
X-RateLimit-Limit
X-RateLimit-Remaining
Retry-After
```

when relevant.

For a future multi-node deployment, the limiter storage can be replaced behind the same policy without changing API contracts.

## CORS

CORS is disabled by default for unknown origins.

Allowed browser origins are explicitly configured:

```php
'allowed_origins' => [
    'https://example-diocese.ru',
],
```

ChurchCMS never uses `Access-Control-Allow-Origin: *` for authenticated partner access.

Server-to-server requests do not require CORS.

## Current bootstrap endpoints

```
GET /api/v1/meta
GET /api/v1/partner/ping
```

`/api/v1/meta` verifies the public API runtime.

`/api/v1/partner/ping` verifies Bearer authentication and reports the authenticated partner/scopes.

The domain endpoints will be registered by their modules as those modules are implemented.

## Security rules

External API endpoints must:

1. be read-only unless a future write integration is explicitly designed and audited;
2. use DTO/API resources;
3. filter publication visibility at the query/service boundary;
4. use prepared SQL;
5. apply bounded pagination;
6. avoid filesystem paths and secrets in errors;
7. avoid stack traces in production responses;
8. enforce scopes before partner data access;
9. log authentication/security events without logging plaintext tokens;
10. support token rotation and revocation.

Direct remote SQL connections to the ChurchCMS database are not a supported integration mechanism.
