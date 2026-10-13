<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

interface ChannelOperationalStatusProvider
{
    /**
     * @return array{
     *     level:string,
     *     inbound_ready:bool,
     *     outbound_ready:bool,
     *     code:string,
     *     message:string
     * }
     */
    public function operationalStatus(): array;
}
