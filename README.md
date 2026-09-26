# ChurchCMS

Modern CMS platform for Orthodox parishes, cathedrals, monasteries and theological schools.

## Project status

Project initialization / legacy audit.

### Reference targets

- Legacy VPDS CMS dump — former Voronezh Theological Seminary CMS, later adapted for a parish/cathedral site.
- Vladimir Icon of the Mother of God Cathedral site — parish profile pilot candidate.
- Tambov Theological Seminary — education profile reference/pilot candidate.
- Annunciation Cathedral of Voronezh — parish/cathedral reference.

## Planned product profiles

- Parish / Cathedral
- Monastery
- Theological school / Seminary
- Mixed profile

## Repository structure

```
app/          HTTP/application layer
core/         dependency-free PHP 8.3+ runtime
modules/      isolated CMS/domain modules
themes/       replaceable presentation packages
config/       runtime configuration
docs/         architecture and integration documentation
legacy/       migration/reference notes
```

## Implemented foundations

- standalone PHP 8.3+ runtime without mandatory Composer/vendor dependencies;
- router, request/response and PDO database infrastructure;
- isolated module manifests/runtime providers;
- inheritable theme/template system;
- modernized legacy-inspired responsive default theme;
- versioned external API foundation for diocesan/partner integrations;
- scoped Bearer API keys stored by hash;
- generic publication syndication engine;
- RSS 2.0 and Rambler/News feed renderers.

Documentation:

- `docs/ARCHITECTURE.md`
- `docs/THEMES.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/API.md`
- `docs/SYNDICATION.md`

The historical dump is kept as a migration/reference artifact and must not be deployed as production code.

## Security note

The legacy source contains outdated authentication, SQL access, file-upload components and potentially sensitive historical data. Do not expose the dump publicly or deploy it under a web root.
