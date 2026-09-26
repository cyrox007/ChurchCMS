# Legacy VPDS CMS dump

## Source artifact

- Local archive name: `www_all_site_vpds.ru.zip`
- Size: ~29 MiB
- SHA-256: `138edaaa1cb1eaf9a186003f1dd598e253df7aae62a118fae9f684eabb500d4e`

## Why the archive is not committed here

This repository is currently **public**. The audited legacy dump contains historical authentication material and other sensitive data, including a credentials file, an SQL dump, and legacy configuration/authentication code.

The raw archive must therefore **not** be committed to a public Git repository.

Before storing the source in GitHub, use one of these approaches:

1. make the repository private and upload the original archive as a controlled legacy artifact; or
2. create a sanitized source snapshot with credentials, database contents, uploads and secrets removed.

## Intended use

The legacy code is a reference specification for migration only. It must never be deployed as the production version of ChurchCMS.

Planned reverse-engineering targets:

- content tree and content types;
- users and administrative roles;
- media/files;
- news/publications;
- galleries;
- search;
- imports;
- URL compatibility and redirects;
- database migration.
