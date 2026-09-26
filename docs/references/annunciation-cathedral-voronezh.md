# Reference audit: Annunciation Cathedral, Voronezh

Public site: https://sobor-vrn.ru/

## Why it is useful

The site is a strong reference for the Parish/Cathedral product profile because it combines a high-volume news feed with structured cathedral information and active parish departments.

## Public information architecture observed

### Cathedral
- News
- History
- Shrines
- Clergy
- Sacraments / rites
- House church

### Departments and ministries
- Educational ministry
- Youth ministry
- Social ministry
- Sunday school
- Library

### Operational content
- Worship schedule
- Media/video
- Articles and publications
- Donations / prayer-note flows
- Contacts

## CMS implications

ChurchCMS should model the following as structured entities rather than hand-built pages:

- WorshipService / Schedule
- Person + Clergy role
- Shrine
- Ministry / Department
- Publication
- Media item
- Sunday-school activity
- Article
- Sacrament information
- Prayer request / donation integration

## Notable reference patterns

- The worship schedule is date-based and includes liturgical commemorations and multiple services per day.
- Departments have their own descriptions, contacts and news streams.
- The home page aggregates news, media, Sunday school and articles.
- Shrines form a hierarchy (for example relic shrines, reliquaries, icons).
- The site exposes prayer-note / moleben use cases that should be isolated from the ordinary content layer because they can involve personal data and payments.

## Architecture inference

Public URL patterns such as `/upload/iblock/...` and pagination parameters like `PAGEN_2` are characteristic of 1C-Bitrix deployments. Treat this as a likely implementation detail, not a confirmed server-side audit, because only the public site was inspected.

## Product conclusion

This reference reinforces a modular ChurchCMS model:

- Core CMS
- Parish/Cathedral profile
- Worship calendar
- Clergy and people directory
- Shrines
- Ministries
- Sunday school
- Media
- Publications
- Integrations for donations and prayer notes
