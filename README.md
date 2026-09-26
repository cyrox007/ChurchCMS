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
docs/
  legacy/
  references/
legacy/
  README.md
```

The historical dump is kept as a migration/reference artifact and must not be deployed as production code.

## Security note

The legacy source contains outdated authentication, SQL access, file-upload components and potentially sensitive historical data. Do not expose the dump publicly or deploy it under a web root.
