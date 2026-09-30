<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use RuntimeException;

final class NativeMailPasswordResetTransport implements
    PasswordResetTransport
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $siteName,
    ) {
    }

    public function deliver(
        PasswordResetMessage $message,
    ): void {
        if (
            !filter_var(
                $message->email,
                FILTER_VALIDATE_EMAIL,
            )
            || !filter_var(
                $this->fromAddress,
                FILTER_VALIDATE_EMAIL,
            )
        ) {
            throw new RuntimeException(
                'Некорректная почтовая настройка восстановления пароля.'
            );
        }

        $siteName = str_replace(
            ["\r", "\n"],
            ' ',
            trim($this->siteName),
        );
        $subject = 'Восстановление доступа — '
            . ($siteName !== '' ? $siteName : 'ChurchCMS');

        $body = implode("\r\n", [
            'Здравствуйте, ' . $message->displayName . '.',
            '',
            'Для восстановления доступа откройте ссылку:',
            $message->resetUrl,
            '',
            'Ссылка действует до '
                . $message->expiresAt
                . ' UTC и может быть использована только один раз.',
            '',
            'Если вы не запрашивали восстановление, проигнорируйте это письмо.',
        ]);

        $headers = implode("\r\n", [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->fromAddress,
            'Auto-Submitted: auto-generated',
            'X-Auto-Response-Suppress: All',
        ]);

        if (!mail(
            $message->email,
            $subject,
            $body,
            $headers,
        )) {
            throw new RuntimeException(
                'Почтовый транспорт не подтвердил отправку.'
            );
        }
    }
}
