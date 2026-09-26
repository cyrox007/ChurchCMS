# Base design system

The bundled `default` theme modernizes recognizable visual cues from the legacy VPDS design while remaining a reusable parent theme for parishes, cathedrals and theological schools.

## Legacy cues retained

The audited legacy stylesheet used:

- deep blue `#003366` as the structural/header color;
- burgundy `#660000` for links and emphasis;
- blue-gray `#9AA7BA` around the central page;
- an architectural building image tinted into the blue header;
- a formal academic visual tone.

The new theme keeps that identity at the level of palette, hierarchy and atmosphere.

## What is removed

The modern theme deliberately drops:

- fixed 775 px page width;
- table-based layout;
- image-sliced shadows;
- 10–13 px interface typography;
- beveled navigation graphics;
- GIF-driven menus;
- desktop-only density.

## Modern palette

```
navy-950  #071d36
navy-900  #0b315d
navy-800  #164574
wine-800  #6b1f2d
wine-700  #842a3c
canvas    #f5f1e9
paper     #fffdf9
ink-950   #18202a
ink-700   #45505d
```

A child theme may replace every token.

## Typography

No external font service is required.

Display headings use a locally available serif stack:

```
Georgia, "Times New Roman", serif
```

Interface/body text uses the platform system sans-serif stack.

A child theme may bundle local font files, but should not require a third-party CDN to render correctly.

## Layout and responsive rules

- mobile-first;
- usable at 320 px without required horizontal scrolling;
- fluid content container;
- large editorial type with bounded line length;
- grids collapse cleanly to one column;
- touch-friendly interactive targets;
- no visual behavior depends on hover alone.

## Accessibility baseline

The default theme includes:

- skip navigation link;
- visible `:focus-visible`;
- semantic landmarks;
- high-contrast structural colors;
- `prefers-reduced-motion`;
- `prefers-contrast`;
- responsive typography via `clamp()`.

## Architectural-photo motif

The old header used a blue-tinted photograph of the seminary building.

The default theme keeps the architectural atmosphere using lightweight CSS geometry so it does not become coupled to one organization.

A site-specific child theme may replace the backdrop with its own locally stored:

- cathedral/church photograph;
- seminary facade;
- monastery image;
- iconographic detail;
- restrained texture.

That asset belongs to the child theme, never to CMS business logic.

## Recommended child themes

```
themes/vladimir-cathedral/
themes/annunciation-cathedral/
themes/tambov-seminary/
```

Each inherits `default` and usually overrides:

- palette/tokens;
- header identity;
- home-page composition;
- selected logical templates;
- local imagery;
- optionally locally bundled fonts.

Do not fork modules to create a visual variant.
