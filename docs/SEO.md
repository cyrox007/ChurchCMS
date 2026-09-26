# SEO and social presentation

ChurchCMS treats SEO as a core public-content contract.

## Automatic defaults

Normal editors should not need to understand SEO.

For a publication, ChurchCMS automatically uses:

- publication title -> HTML/search/social title;
- excerpt -> meta/social description;
- stable public URL -> canonical and Open Graph URL;
- publication type -> Article/NewsArticle semantic type;
- publication/modified timestamps -> semantic and article metadata;
- author -> author metadata.

Manual overrides are under the collapsed **SEO and social card** section.

## Publication overrides

Stored separately from the Publication domain:

- SEO title;
- SEO description;
- optional keywords;
- canonical URL;
- social card title;
- social card description;
- social image URL;
- robots index;
- robots follow.

This keeps third-party/search presentation concerns out of core editorial content.

## Search metadata

The default theme emits:

- `<title>`;
- `description`;
- `robots`;
- `yandex`;
- canonical link;
- optional keywords;
- author.

## Social metadata

The default theme emits:

- Open Graph title/type/site/locale/url/description/image;
- article published/modified/author/section properties;
- Twitter/X card title/description/image.

No third-party JavaScript SDK is required.

## Structured content

Publication markup uses Schema.org microdata:

- `NewsArticle` for news;
- `Article` for other publication types;
- headline;
- mainEntityOfPage;
- datePublished;
- dateModified;
- author;
- articleBody;
- description.

When Media is implemented, representative images and video metadata will be added to the structured-data contract.

## robots.txt

`GET /robots.txt`

The generated file:

- allows public crawling;
- excludes admin/API/install paths;
- advertises the absolute Sitemap URL.

## Sitemap

`GET /sitemap.xml`

Includes:

- home;
- publication archive;
- published publications;
- last modification timestamps.

Only already-public publication records are exposed.

## Share controls

Publication pages contain:

- native Web Share button when the browser supports it;
- configurable provider links;
- copy-link action.

Default lightweight providers:

- Telegram;
- VK;
- Odnoklassniki.

Providers are configured by URL template and can be added/disabled without a third-party widget SDK.

## Performance

SEO metadata is rendered server-side and stored in full-page cache.

Changing publication SEO bumps the public cache version immediately.

robots/sitemap are excluded from HTML page-cache because they have their own MIME/cache policy.

## Validation before production

Release/pilot validation should include:

- Google Rich Results/URL Inspection;
- Yandex Webmaster validation;
- sitemap XML validation;
- canonical checks;
- Open Graph preview tests;
- noindex checks for private/admin pages.
