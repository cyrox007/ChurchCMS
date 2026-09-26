# Theme and template system

ChurchCMS themes are standalone presentation packages. A theme controls HTML, CSS, JavaScript and visual composition. It must not contain business rules, database queries or CMS-domain mutations.

The goal is simple: two ChurchCMS installations may use the same modules and the same content model while looking completely different.

## 1. Theme package

Every theme lives in:

```
themes/<theme-id>/
  theme.json
  templates/
    layouts/
    pages/
    publications/
    worship/
    people/
    partials/
    components/
  assets/
    css/
    js/
    images/
    fonts/
```

Only `theme.json` is mandatory. The rest depends on the templates the theme implements.

Theme IDs use lowercase ASCII letters, numbers, dots, underscores and hyphens.

## 2. Manifest

Example:

```json
{
  "id": "cathedral-voronezh",
  "name": "Cathedral Voronezh",
  "version": "1.0.0",
  "parent": "default",
  "profiles": ["cathedral"],
  "templates": {
    "layout.main": "layouts/main",
    "page.home": "pages/home",
    "publication.news": "publications/news",
    "worship.index": "worship/index",
    "partial.header": "partials/header",
    "component.card": "components/card"
  }
}
```

The `parent` field is optional.

A child theme only needs to map templates it wants to replace. Missing logical templates fall back through the parent chain.

Inheritance cycles are rejected during theme registry boot.

## 3. Logical template names

Application and module code use logical names, never physical template paths.

Good:

```php
$renderer->page('publication.news', $data);
```

Bad:

```php
require '/themes/cathedral/templates/publications/news.php';
```

Recommended namespaces:

```
layout.*
page.*
publication.*
worship.*
person.*
shrine.*
ministry.*
event.*
media.*
partial.*
component.*
education.*
```

A module should document every logical template name it expects a theme to provide.

## 4. Rendering a page

Controllers pass presentation data to the theme:

```php
ThemeRenderer::fromConfig()->page('publication.news', [
    'title' => $publication->title,
    'publication' => $publication,
]);
```

The renderer first renders `publication.news`, then injects its trusted rendered HTML into `layout.main` as `$content`.

A controller must not know whether the active theme uses a sidebar, a full-width hero, cards, grids or another visual system.

## 5. Escaping output

Template variables are not automatically trusted.

Use:

```php
<h1><?= $theme->e($title) ?></h1>
```

`$theme->e()` escapes with UTF-8, `ENT_QUOTES` and `ENT_SUBSTITUTE`.

Do not print editor/user values directly:

```php
<?= $title ?>        // wrong for untrusted text
```

Already rendered internal template output, such as `$content`, a partial or a component result, may be printed as HTML:

```php
<?= $content ?>
```

Rich content from the CMS will receive a separate trusted/sanitized content type before the content module is considered production-ready.

## 6. Partials

Partials are reusable theme fragments.

Manifest:

```json
{
  "templates": {
    "partial.header": "partials/header"
  }
}
```

Template:

```php
<?= $theme->partial('partial.header', [
    'siteName' => $siteName,
]) ?>
```

Use partials for structural fragments such as:

- header;
- footer;
- breadcrumbs;
- pagination;
- navigation;
- article metadata.

## 7. Components

Components are small reusable UI units.

Call:

```php
<?= $theme->component('component.card', [
    'title' => 'Новости',
    'text' => 'Последние публикации',
]) ?>
```

Inside the component:

```php
<article class="card">
    <h2><?= $theme->e($props['title'] ?? '') ?></h2>
</article>
```

Components also support named slots:

```php
<?= $theme->component(
    'component.card',
    ['title' => 'Расписание'],
    ['default' => $trustedRenderedContent],
) ?>
```

A slot is considered already rendered internal HTML. Do not pass raw user input as a slot.

## 8. Layouts

Layouts define the document shell.

Example:

```php
<!doctype html>
<html lang="ru">
<head>
    <title><?= $theme->e($title ?? '') ?></title>
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/site.css')) ?>">
</head>
<body>
    <?= $theme->partial('partial.header') ?>
    <main><?= $content ?></main>
    <?= $theme->partial('partial.footer') ?>
</body>
</html>
```

A theme may provide multiple layouts, for example:

```
layout.main
layout.landing
layout.article
layout.education
layout.print
```

Controllers or presentation services choose a logical layout contract, not a file.

## 9. Assets

Theme assets are stored under:

```
themes/<id>/assets/
```

Use:

```php
$theme->asset('css/site.css')
$theme->asset('images/logo.svg')
```

Do not hard-code filesystem paths or theme IDs.

Assets are delivered through the controlled `/_theme-asset` endpoint.

The endpoint:

- resolves paths with `realpath`;
- blocks path traversal;
- refuses PHP and arbitrary file extensions;
- sends an explicit MIME type;
- enables `nosniff`;
- supports long-lived caching.

Child themes also inherit missing assets from parent themes.

## 10. Creating a new design

Minimal child theme:

```
themes/my-parish/
  theme.json
  templates/
    layouts/main.php
  assets/
    css/site.css
```

`theme.json`:

```json
{
  "id": "my-parish",
  "name": "My Parish",
  "version": "1.0.0",
  "parent": "default",
  "profiles": ["parish"],
  "templates": {
    "layout.main": "layouts/main"
  }
}
```

Then configure:

```php
'theme' => [
    'active' => 'my-parish',
],
```

Everything not overridden by `my-parish` is taken from `default`.

This is the preferred way to create a site-specific design.

## 11. Full custom theme

A full custom theme may omit `parent` and provide every required logical template itself.

This is suitable when a cathedral, monastery or seminary has a completely unique design system.

A theme without a parent is responsible for all contracts used by enabled modules.

## 12. Theme versus content

A theme controls:

- markup;
- visual hierarchy;
- CSS;
- client-side enhancement;
- placement of presented data;
- component composition.

A theme must not control:

- SQL;
- authentication;
- permissions;
- publication state;
- content mutations;
- payment logic;
- worship-calendar business rules;
- file storage rules.

These belong to core/modules.

## 13. Site profile versus theme

A profile and a theme are different concepts.

Profile:

```
cathedral
education
parish
```

determines enabled domain capabilities/modules.

Theme:

```
cathedral-voronezh
seminary-classic
minimal-parish
```

determines visual presentation.

Any compatible theme can be used with any profile for which it provides the required logical templates.

## 14. Recommended development workflow

1. Start from the `default` theme.
2. Create a child theme.
3. Override only the logical templates that must change.
4. Keep CSS/JS/images inside the theme.
5. Never modify CMS core to achieve a visual change.
6. If a view needs new data, extend the presentation contract in the corresponding module instead of querying from a template.
7. Document new logical template keys in the module documentation.
8. Test the theme with missing/empty optional content.

## 15. Compatibility rules

A theme should declare a semantic version.

Breaking presentation-contract changes in ChurchCMS must be documented before release.

Future theme diagnostics will verify:

- required logical templates;
- parent availability;
- missing assets;
- incompatible profile requirements;
- template contract version.

## 16. Security rules for theme authors

Never:

- execute user-uploaded PHP;
- include a file path supplied by a request;
- call `eval`;
- access `$_GET`, `$_POST` or `$_FILES` directly from templates;
- run SQL from templates;
- print untrusted values without `$theme->e()`;
- place secrets in `theme.json` or assets.

Templates receive only the data explicitly supplied by the application.


## 17. Named routes in templates

Templates should not hard-code internal application URLs.

Use:

```php
<a href="<?= $theme->e($theme->route('home')) ?>">Главная</a>
```

Route parameters:

```php
$theme->route('publication_show', ['slug' => $publication->slug])
```

Extra values that are not route placeholders are encoded as an RFC 3986 query string.

This keeps themes compatible when URL structures change.
