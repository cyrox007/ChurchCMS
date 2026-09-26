# Reference audit: Annunciation Cathedral, Voronezh

Public site: https://sobor-vrn.ru/

## Why it is useful

The site is a strong reference for the Parish/Cathedral product profile because it combines a high-volume news feed with structured cathedral information, worship operations, active parish departments, media, donations and prayer-note workflows.

## Public information architecture observed

### Cathedral
- News
- History
- Shrines
- Clergy
- Sacraments / rites
- House church
- Saints / hagiographic materials

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
- Donations
- Prayer notes / molebens
- Contacts

## CMS implications

ChurchCMS should model the following as structured entities rather than hand-built pages:

- WorshipService / WorshipSchedule
- Person + ClergyRole
- Shrine + ShrineCategory
- Saint / Hagiography
- Ministry / Department
- Publication / News
- Article
- MediaItem / Gallery / Video
- SundaySchoolActivity
- SacramentInformation
- DonationCampaign / DonationIntegration
- PrayerRequest / MolebenRequest

## Worship schedule

The public schedule is date-based, includes old-style dates and liturgical commemorations, and supports multiple services per day.

This argues for a real worship-calendar model rather than an editable HTML table:

- service date;
- start time;
- service type;
- church / chapel;
- liturgical commemoration;
- notes;
- recurring rules;
- exceptions;
- publication status.

## Clergy directory

The clergy section has a structured list with role/title and individual biography pages.

ChurchCMS should therefore keep a single Person entity with reusable roles. A person may simultaneously be clergy, an author, a department lead, a teacher or another organizational role.

## Shrines

Shrines are hierarchical. Public examples include categories such as relic shrines, reliquaries and icons, with individual objects below them.

The CMS should support:

- ShrineCategory;
- Shrine;
- description/history;
- images;
- physical location;
- associated saints;
- related publications;
- sort order.

## Ministries and departments

Departments have their own descriptions, contacts and independent news streams.

A Ministry/Department should therefore support:

- title;
- description;
- leader/contact person;
- phones/social links;
- publications/news;
- events;
- media;
- related pages.

This can cover youth work, social ministry, educational work, Sunday school, library and similar parish activities without adding a new hard-coded module for every department.

## Donations and prayer notes

The cathedral supports card donations and bank-transfer information. It also exposes online prayer-note / moleben flows that collect names and a donation amount.

These workflows must be isolated from normal CMS content because they can involve:

- personal data;
- payment providers;
- consent text;
- transactional records;
- audit requirements;
- anti-spam and abuse controls.

The content CMS should configure the public presentation and integration, while payment processing and sensitive submissions should use a dedicated service/module.

## Home-page aggregation

The public home page combines several independent streams:

- current news;
- video/media;
- prayer/moleben calls to action;
- Sunday-school news;
- articles/publications.

ChurchCMS should therefore provide configurable home-page blocks fed by structured content instead of embedding specific section logic into the template.

## Media and publications

The cathedral uses embedded external video and also publishes long-form articles and PDF publications. Media should therefore support external providers as well as locally stored files.

## Likely current implementation

Public URL patterns such as:

- `/upload/iblock/...`
- pagination parameters like `PAGEN_2`
- explicit `index.php` variants

are characteristic of 1C-Bitrix sites. Treat this as a strong public-facing implementation inference, not a confirmed server-side audit, because no source code or administrative access was inspected.

## Product conclusion

This reference reinforces a modular ChurchCMS model:

- Core CMS
- Parish/Cathedral profile
- Worship calendar
- Clergy and people directory
- Shrines and saints
- Ministries / departments
- Sunday school
- Media
- Publications
- Donation integrations
- Prayer-note / moleben integrations

It also suggests having at least two parish presets:

1. **Small parish** — pages, news, clergy, schedule, contacts, media.
2. **Cathedral / large parish** — adds departments, shrines, saints, donation campaigns, prayer-note workflows, richer media and multiple content streams.
