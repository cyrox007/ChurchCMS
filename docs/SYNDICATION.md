# Publication syndication

ChurchCMS separates publication creation from external distribution.

A publication may be public on the local website while remaining excluded from all external feeds. External distribution is an explicit editorial choice.

## Editorial model

The future Publications module will expose syndication controls similar to:

```
External distribution
[ ] General RSS
[ ] Diocesan API
[ ] Rambler/News
[ ] Other approved partner
```

Default for a newly created publication:

```
website: published according to normal workflow
external syndication: disabled
```

This prevents accidental distribution of local notices, internal material or content without the necessary rights.

## Domain contract

External feed providers return `SyndicationEntry` objects.

An entry contains:

- stable public identifier;
- canonical absolute URL;
- title;
- annotation;
- approved feed HTML;
- publication timestamp;
- optional update timestamp;
- author;
- categories;
- optional main image;
- an explicit list of allowed targets.

Example targets:

```
rss
diocese
rambler
smi2
mail-news
```

A target string does not imply that an external platform has accepted the website as a partner. It only represents an export destination configured in ChurchCMS.

## Provider architecture

Modules do not write RSS/XML directly.

A publication-capable module implements:

```php
ChurchCMS\Core\SyndicationProvider
```

and registers it with:

```php
SyndicationRegistry::register('publications', $provider);
```

The registry filters entries by target and sorts them by publication date.

## Renderers / adapters

Current renderers:

- `Rss2SyndicationRenderer` — generic RSS 2.0;
- `RamblerSyndicationRenderer` — target-specific RSS for Rambler/News.

Future integrations should be added as adapters/renderers rather than adding portal-specific fields throughout the Publication domain model.

This allows ChurchCMS to support a portal changing its import contract without redesigning Publications.

## Current feed routes

```
GET /feeds/rss.xml
GET /feeds/rambler.xml
```

The general RSS target is enabled by default.

The Rambler target is disabled by default and should be enabled only when the site is ready for external distribution.

## Rambler/News

The current public Rambler/News feed specification uses RSS 2.0.

The implemented adapter emits:

- UTF-8 XML declaration;
- RSS 2.0 document;
- channel title/link/description;
- stable guid;
- title;
- canonical link;
- RFC-2822 publication date;
- description;
- category;
- author;
- enclosure for a main image;
- full `content` inside CDATA;
- the Rambler XML namespace.

Before production onboarding, the generated feed must be validated against the current partner requirements because third-party contracts can change independently of ChurchCMS releases.

## News Mail

News Mail currently works with partner media and uses an application/partner-review process.

ChurchCMS therefore does not pretend that simply enabling a feed guarantees import into News Mail.

If accepted as a partner, an adapter can be configured to the exact data contract supplied by the platform without changing the publication storage model.

## СМИ2 and other aggregators

СМИ2 remains a major Russian news aggregator and partner network.

Its integration should be implemented as a separate adapter after the current partner/import specification is obtained. The generic `SyndicationEntry` model already contains the information normally required by such integrations.

## Canonical URLs

Every syndicated publication must have a stable canonical absolute URL.

Changing a visual theme must not change publication identity.

The feed must never link directly to:

- storage paths;
- temporary preview URLs;
- admin URLs;
- unpublished revisions.

## Rights and editorial responsibility

Syndication is a deliberate editorial action.

ChurchCMS should allow editors to exclude a publication when:

- image/content rights do not permit redistribution;
- material is local-only;
- it contains personal information unsuitable for external aggregation;
- the external platform's category or editorial policy is not appropriate;
- the publication is a correction, draft or internal notice.

Future Publication UI should show where an item is distributed and when each target last exported it.

## Target-specific overrides

A later Publications milestone may allow optional overrides such as:

```
syndication_title
syndication_excerpt
syndication_category
syndication_image
```

These are presentation/export overrides only. They do not create a second copy of the publication.

## Withdrawal and corrections

If a publication is unpublished or withdrawn:

- it must disappear from normal current feeds;
- partner API consumers should receive a tombstone/withdrawal event when supported;
- export logs should preserve the fact that it was previously syndicated.

Corrections should retain the same stable identity unless the external partner contract explicitly requires otherwise.

## Security

Feed generation must:

- operate only on published/approved data;
- use explicit DTOs, not raw database rows;
- never expose internal IDs or filesystem paths;
- never expose unpublished media;
- generate valid UTF-8 XML;
- escape XML values;
- isolate target-specific formatting in adapters;
- avoid remote callbacks during page publication.

External platforms pull feeds over HTTP. Publishing content must not block while waiting for a third-party service.
