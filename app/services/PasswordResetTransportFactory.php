<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\Config;

final class PasswordResetTransportFactory
{
    public static function fromConfig(): PasswordResetTransport
    {
        $transport = strtolower(trim((string) Config::get(
            'security.password_reset.transport',
            'disabled',
        )));

        if ($transport !== 'mail') {
            return new UnavailablePasswordResetTransport();
        }

        return new NativeMailPasswordResetTransport(
            fromAddress: trim((string) Config::get(
                'security.password_reset.mail_from',
                '',
            )),
            siteName: trim((string) Config::get(
                'site.name',
                Config::get('app.name', 'ChurchCMS'),
            )),
        );
    }
}
