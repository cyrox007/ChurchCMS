# External Channels

The `social` module is evolving into a provider-agnostic External Channels subsystem.

It handles social networks, messengers and video hosting platforms through the same adapter boundary.

## Why it is not a fixed provider list

A church or theological school may use:

- VK;
- Telegram;
- MAX;
- Odnoklassniki;
- Dzen;
- YouTube;
- Rutube;
- regional/diocesan platforms;
- future platforms that do not exist yet.

Therefore `provider` is an open validated identifier, not a database enum.

## Capabilities

An adapter declares what it supports:

```
publish.text
publish.link
publish.image
publish.video
remote.update
remote.delete
import.posts
import.video
sync.webhook
sync.polling
```

The UI must show only operations actually supported by the selected adapter.

## Outbound flow

```
Publication approved/published
        |
editor selects external channels
        |
outbox records created
        |
background dispatcher
        |
provider adapter
        |
remote IDs/status/errors saved
```

A slow/down remote platform never delays the public website.

## Inbound flow

```
provider webhook/polling
        |
adapter converts remote payload
        |
generic ChannelInboundItem
        |
external_channel_items inbox
        |
operator review
        |
ignore / link / import as draft
```

Default inbound policy is `review`.

## Source of truth

ChurchCMS official publication is authoritative for official website news.

External channels may contain content that is intentionally absent from the website.

External edits never silently replace official content.

## Loop prevention

Every inbound item stores:

- connection;
- remote ID;
- fingerprint;
- remote timestamps;
- canonical URL;
- linked publication ID.

A connection + remote ID is unique.

Imported/linked items can therefore be recognized when outbound synchronization later sees the same remote object.

## Security

- credentials encrypted at rest with AES-256-GCM;
- encryption key generated during installation and stored in local configuration;
- credentials never exposed to public API/templates;
- adapter HTTP client requires HTTPS;
- webhook adapters must validate provider signatures/secrets when available;
- external raw payload is never directly rendered as trusted HTML.

## Video

Video is a first-class inbound/outbound content kind.

The channel contract supports `video`, but binary upload/transcoding belongs to the future Media subsystem.

Adapters such as YouTube/Rutube can first import metadata/links and later publish local Media assets through resumable upload workflows.

## Built-in adapters

Planned first:

- Telegram;
- VK;
- MAX;
- YouTube;
- Rutube.

Other adapters can be separate modules and register themselves with `ChannelAdapterRegistry` without database/core changes.
