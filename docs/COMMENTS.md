# Publication comments

Comments are implemented as the optional `comments` module.

## Per-publication control

Every publication has:

```
comments_enabled
```

The default is `false`.

In the publication editor the operator sees one switch:

> Разрешить комментарии

If it is off:

- no comment list is rendered;
- no comment form is rendered;
- direct submission attempts return 404.

If it is on and the comments module is globally enabled:

- approved comments appear below the publication;
- visitors may submit a comment;
- new comments are premoderated by default.

## Public submission

Initial comments are intentionally plain text.

Fields:

- display name;
- optional email;
- comment text.

Email is never rendered publicly and is not exposed through the public/partner API.

Public submission has:

- CSRF protection;
- per-IP/path rate limiting;
- maximum length;
- HTML stripping;
- premoderation.

## Moderation

Statuses:

- pending;
- approved;
- rejected;
- spam.

Moderators with `comments.moderate` see a simple queue in:

```
/admin/comments
```

For each item the UI shows:

- publication title;
- author name;
- timestamp;
- optional contact email;
- comment text;
- three actions: Approve / Reject / Spam.

Moderation actions are written to the audit log.

## Global switch

`config/app.php` also contains:

```php
'comments' => [
    'enabled' => true,
    'moderation' => 'premoderated',
]
```

This allows an installation to disable the comments subsystem globally even when historical publications have their per-publication switch enabled.

## Syndication

Comments are never included in RSS, Rambler or diocesan publication export by default.
