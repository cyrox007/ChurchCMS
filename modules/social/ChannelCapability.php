<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class ChannelCapability
{
    public const PUBLISH_TEXT = 'publish.text';
    public const PUBLISH_LINK = 'publish.link';
    public const PUBLISH_IMAGE = 'publish.image';
    public const PUBLISH_VIDEO = 'publish.video';
    public const UPDATE_REMOTE = 'remote.update';
    public const DELETE_REMOTE = 'remote.delete';
    public const IMPORT_POSTS = 'import.posts';
    public const IMPORT_VIDEO = 'import.video';
    public const WEBHOOK = 'sync.webhook';
    public const POLLING = 'sync.polling';
}
