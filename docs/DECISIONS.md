# ChurchCMS architecture decisions

This is a concise decision log. Detailed ADR files can be introduced when decisions become more complex.

## D-001 — PHP 8.3 minimum

Accepted.

ChurchCMS targets PHP 8.3 or newer.

## D-002 — No mandatory external runtime dependencies

Accepted.

The CMS core must boot without Composer/vendor, frameworks or mandatory CDN/runtime services.

## D-003 — PostgreSQL primary, MySQL 8 compatibility where practical

Accepted.

PDO is the only database primitive used by application infrastructure.

## D-004 — Modular runtime inspired by Notes

Accepted.

ChurchCMS uses module manifests, registry and runtime providers. Notes is an architecture reference, not a codebase to copy wholesale.

## D-005 — Replace legacy VPDS runtime instead of incrementally modernizing it

Accepted.

Legacy code/database are migration/reference sources only.

## D-006 — Theme system is a first-class subsystem

Accepted.

Every site may use its own child/full theme. Business modules expose presentation contracts through logical template names.

## D-007 — Default theme modernizes legacy VPDS visual cues

Accepted.

Keep recognizable deep-blue/burgundy/architectural character while replacing fixed-width/table-era UI with responsive accessible design.

## D-008 — External systems use HTTP API, never direct DB access

Accepted.

Diocesan sites and other integrations consume versioned explicit API resources.

## D-009 — Publication syndication is explicit opt-in

Accepted.

Publishing on the local site does not automatically imply distribution to third-party aggregators.

## D-010 — External portal support uses adapters

Accepted.

Rambler, СМИ2, News Mail and future platforms must be implemented as independent adapters/renderers so portal-specific requirements do not pollute the core Publication model.

## D-011 — Specialized systems are integrated, not rewritten

Accepted.

Moodle remains LMS. OJS remains journal/publishing workflow. ChurchCMS provides public-site integration.

## D-012 — Multi-site compatibility should be preserved

Accepted.

Core/domain design should avoid assumptions that one process can only represent one website. Per-site theme and configuration are planned.


## D-013 — Web installer is the primary fresh-install experience

Accepted.

ChurchCMS must be installable without Composer or shell access. The installer should auto-detect everything it safely can, create/migrate the database, create the first administrator and then lock itself.

## D-014 — Administration is designed for non-technical operators

Accepted.

Routine editorial work should use plain language, sensible defaults, progressive disclosure and minimal steps. Technical concepts must not leak into normal editorial screens.

## D-015 — Comments are optional and moderated by default

Accepted.

Comments are a separate module attached to publications. They can be disabled globally/per-publication. Initial public submission is plain text and premoderated; external syndication never includes comments by default.
