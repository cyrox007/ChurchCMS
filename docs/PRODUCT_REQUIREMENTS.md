# ChurchCMS product requirements

This document is the durable product memory for decisions made during project initialization.

## Product purpose

ChurchCMS is a reusable CMS for:

- Orthodox parishes and churches;
- cathedrals and large parishes;
- monasteries;
- theological seminaries and schools;
- mixed church/education organizations.

The product must not be tied to one institution or one visual design.

## Runtime constraints

- PHP 8.3 minimum.
- No mandatory external runtime dependencies.
- No framework dependency.
- No mandatory Composer/vendor tree.
- Standard PHP extensions only.
- PDO for database access.
- PostgreSQL is the primary database target.
- MySQL 8 compatibility should be preserved where reasonable.
- No mandatory npm/build pipeline for the public site.

## Architecture principles

- modular domain architecture;
- business logic separated from presentation;
- themes are replaceable packages;
- modules register capabilities/routes/providers through runtime contracts;
- database access uses prepared statements;
- external integrations never receive direct database access;
- explicit migration subsystem;
- legacy VPDS code is a migration/reference source, not a runtime foundation.

## Product profiles

### Small Parish

- pages;
- news/publications;
- clergy;
- worship schedule;
- media;
- contacts.

### Parish / Cathedral

Adds:

- ministries/departments;
- events;
- shrines;
- saints;
- Sunday school;
- library;
- richer media;
- donations;
- prayer-note/moleben integrations.

### Theological School

Adds:

- educational programs;
- teachers;
- departments/chairs;
- admissions;
- schedules;
- science/publications;
- documents;
- educational-organization disclosure/compliance;
- Moodle integration;
- OJS integration.

### Mixed profile

Can enable parish/cathedral and education capabilities together.

## Themes and design

Every installation may have a unique design.

Requirements:

- themes are isolated from business logic;
- logical template names instead of hard-coded paths;
- child-theme inheritance;
- layouts, partials, components and slots;
- local CSS/JS/images/fonts;
- no required third-party CDN;
- responsive/mobile-first baseline;
- accessible navigation/focus/contrast/reduced motion;
- per-site theme selection for future multi-site usage.

The default theme modernizes the visual language of the legacy VPDS site:

- deep blue structural color;
- burgundy accent;
- architectural/academic character;
- modern responsive layout;
- larger typography and whitespace;
- no 2000s-era fixed-width/table/GIF UI.

## External API

ChurchCMS must safely expose selected data to external systems such as a diocesan website.

Requirements:

- versioned API namespace;
- public read-only API for already published data;
- trusted partner API with Bearer tokens;
- token hashes stored instead of plaintext tokens;
- scopes;
- revocation/disable;
- optional origin allowlists;
- rate limiting;
- bounded pagination;
- stable public IDs;
- explicit API DTO/resources;
- incremental synchronization;
- withdrawal/tombstone support;
- no raw database-row serialization.

## Publication syndication

Editors must explicitly decide which external channels receive each publication.

Potential targets:

- generic RSS;
- diocesan API;
- Rambler/News;
- СМИ2;
- News Mail and other approved partner platforms.

Default behavior:

- normal site publication follows workflow;
- external syndication is opt-in per publication/target.

The syndication subsystem uses adapters/renderers so external portal requirements do not leak into the Publication storage model.

## Publications

Core publication requirements:

- draft/review/scheduled/published/withdrawn workflow;
- stable public ID;
- slug and canonical URL;
- type (news/article/announcement/sermon/interview/document/etc.);
- title;
- excerpt;
- body;
- author;
- categories/tags later;
- publication date;
- revision/history later;
- SEO later;
- explicit syndication targets;
- external API projection;
- withdrawal support;
- no unpublished content in public feeds/API.

## Worship

Worship schedule is a structured domain model, not an HTML table.

Must support:

- date;
- time;
- service type;
- church/chapel;
- liturgical commemoration;
- notes;
- recurring rules;
- exceptions;
- publication status.

## People

One reusable Person entity should support multiple roles:

- clergy;
- teacher;
- author;
- department head;
- administrator;
- other organization roles.

Avoid duplicate person records per role.

## Media

Media is a first-class subsystem because legacy sites contain tens of thousands of files.

Must support:

- originals;
- derivatives/thumbnails;
- MIME/type;
- checksum;
- dimensions/size;
- metadata;
- captions;
- usage references;
- duplicate detection;
- safe upload allowlists;
- no executable uploads.

## Legacy migration

The VPDS legacy system is used to map:

- content tree;
- content types/templates;
- users/roles where appropriate;
- files/images;
- publications;
- galleries;
- URLs;
- imports;
- search metadata.

Migration must preserve useful historical URLs through redirects.

Legacy credentials and secrets must not be copied into public source control.

## Reference targets

- legacy VPDS CMS (former Voronezh Theological Seminary CMS);
- Vladimir Icon of the Mother of God Cathedral — parish pilot candidate;
- Annunciation Cathedral, Voronezh — large parish/cathedral reference;
- Tambov Theological Seminary — education reference/pilot candidate.

## Non-goals for the first MVP

Do not block initial delivery on:

- LMS replacement;
- OJS replacement;
- complex payment processing;
- full forum/comments platform;
- social network features;
- arbitrary plugin marketplace.

Moodle/OJS should be integrated, not reimplemented.
