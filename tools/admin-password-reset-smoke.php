<?php

declare(strict_types=1);

use ChurchCMS\App\Services\AdminPasswordResetService;
use ChurchCMS\App\Services\PasswordResetMessage;
use ChurchCMS\App\Services\PasswordResetTransport;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\ThemeRenderer;

require dirname(__DIR__) . '/core.php';

final class PasswordResetSmokeTransport implements
    PasswordResetTransport
{
    /** @var list<PasswordResetMessage> */
    public array $messages = [];

    public function __construct(
        private readonly bool $fail = false,
    ) {
    }

    public function deliver(
        PasswordResetMessage $message,
    ): void {
        $this->messages[] = $message;

        if ($this->fail) {
            throw new RuntimeException(
                'Тестовая ошибка доставки.'
            );
        }
    }
}

$pdo = DatabaseManager::getInstance()->connection();
$now = gmdate('Y-m-d H:i:s');
$initialPassword = 'Initial-password-123';
$initialHash = password_hash(
    $initialPassword,
    PASSWORD_DEFAULT,
);

if (!is_string($initialHash)) {
    exit(1);
}

$pdo->prepare(
    'INSERT INTO admin_users (
        public_id,
        username,
        password_hash,
        display_name,
        email,
        status,
        auth_version,
        created_at,
        updated_at
     ) VALUES (
        :public_id,
        :username,
        :password_hash,
        :display_name,
        :email,
        :status,
        :auth_version,
        :created_at,
        :updated_at
     )'
)->execute([
    'public_id' =>
        '73000000-0000-4000-8000-000000000001',
    'username' => 'password-reset-smoke',
    'password_hash' => $initialHash,
    'display_name' => 'Тестовый администратор',
    'email' => 'reset@example.invalid',
    'status' => 'active',
    'auth_version' => 7,
    'created_at' => $now,
    'updated_at' => $now,
]);

$transport = new PasswordResetSmokeTransport();
$service = new AdminPasswordResetService(
    $pdo,
    $transport,
    'https://church.example',
    1800,
);

if (
    $service->requestReset(
        'unknown@example.invalid'
    )
    || $transport->messages !== []
) {
    fwrite(
        STDERR,
        "Неизвестный email ошибочно выдал reset-ссылку.\n",
    );
    exit(1);
}

if (!$service->requestReset('reset@example.invalid')) {
    fwrite(
        STDERR,
        "Первый reset-запрос не был доставлен.\n",
    );
    exit(1);
}

if (count($transport->messages) !== 1) {
    fwrite(STDERR, "Первое reset-письмо отсутствует.\n");
    exit(1);
}

$tokenFromUrl = static function (
    PasswordResetMessage $message,
): string {
    $query = parse_url(
        $message->resetUrl,
        PHP_URL_QUERY,
    );

    if (!is_string($query)) {
        return '';
    }

    parse_str($query, $params);

    return is_string($params['token'] ?? null)
        ? $params['token']
        : '';
};

$firstToken = $tokenFromUrl(
    $transport->messages[0],
);

if (
    preg_match(
        '/^[A-Za-z0-9_-]{43}$/D',
        $firstToken,
    ) !== 1
    || !$service->tokenIsValid($firstToken)
) {
    fwrite(STDERR, "Первый reset-токен некорректен.\n");
    exit(1);
}

$stored = $pdo->query(
    'SELECT token_hash
     FROM admin_password_resets
     ORDER BY id DESC
     LIMIT 1'
)->fetchColumn();

if (
    !is_string($stored)
    || $stored === $firstToken
    || $stored !== hash(
        'sha256',
        $firstToken,
    )
) {
    fwrite(
        STDERR,
        "Reset-токен хранится небезопасно.\n",
    );
    exit(1);
}

if (!$service->requestReset('RESET@example.invalid')) {
    fwrite(
        STDERR,
        "Повторный reset-запрос не был доставлен.\n",
    );
    exit(1);
}

$secondToken = $tokenFromUrl(
    $transport->messages[1],
);

if (
    $service->tokenIsValid($firstToken)
    || !$service->tokenIsValid($secondToken)
) {
    fwrite(
        STDERR,
        "Новый запрос не инвалидировал старый reset-токен.\n",
    );
    exit(1);
}

try {
    $service->resetPassword(
        $secondToken,
        'Another-password-123',
        'different-confirmation',
    );
    fwrite(
        STDERR,
        "Несовпадающее подтверждение ошибочно принято.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

if (!$service->tokenIsValid($secondToken)) {
    fwrite(
        STDERR,
        "Ошибка валидации ошибочно погасила reset-токен.\n",
    );
    exit(1);
}

$newPassword = 'Changed-password-456';
$service->resetPassword(
    $secondToken,
    $newPassword,
    $newPassword,
);

if ($service->tokenIsValid($secondToken)) {
    fwrite(
        STDERR,
        "Использованный reset-токен остался активным.\n",
    );
    exit(1);
}

$user = $pdo->query(
    "SELECT password_hash, auth_version
     FROM admin_users
     WHERE username = 'password-reset-smoke'
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (
    !is_array($user)
    || !password_verify(
        $newPassword,
        (string) $user['password_hash'],
    )
    || (int) $user['auth_version'] !== 8
) {
    fwrite(
        STDERR,
        "Reset не сменил пароль или не отозвал старые сессии.\n",
    );
    exit(1);
}

try {
    $service->resetPassword(
        $secondToken,
        'Third-password-789',
        'Third-password-789',
    );
    fwrite(
        STDERR,
        "Одноразовый reset-токен использован повторно.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$service->requestReset('reset@example.invalid');
$thirdToken = $tokenFromUrl(
    $transport->messages[2],
);
$pdo->prepare(
    'UPDATE admin_password_resets
     SET expires_at = :expires_at
     WHERE token_hash = :token_hash'
)->execute([
    'expires_at' => '2000-01-01 00:00:00',
    'token_hash' => hash(
        'sha256',
        $thirdToken,
    ),
]);

if ($service->tokenIsValid($thirdToken)) {
    fwrite(
        STDERR,
        "Истёкший reset-токен остался действительным.\n",
    );
    exit(1);
}

$failedTransport = new PasswordResetSmokeTransport(
    true,
);
$failedService = new AdminPasswordResetService(
    $pdo,
    $failedTransport,
    'https://church.example',
    1800,
);

try {
    $failedService->requestReset(
        'reset@example.invalid'
    );
    fwrite(
        STDERR,
        "Ошибка доставки ошибочно считалась успехом.\n",
    );
    exit(1);
} catch (RuntimeException) {
}

$failedToken = $tokenFromUrl(
    $failedTransport->messages[0],
);

if (
    $failedToken === ''
    || $failedService->tokenIsValid($failedToken)
) {
    fwrite(
        STDERR,
        "Токен неудачной доставки остался активным.\n",
    );
    exit(1);
}

$router = Router::getInstance();
$routes = [
    'admin_password_forgot' =>
        '/admin/password/forgot',
    'admin_password_forgot_submit' =>
        '/admin/password/forgot',
    'admin_password_reset' =>
        '/admin/password/reset',
    'admin_password_reset_submit' =>
        '/admin/password/reset',
];

foreach ($routes as $name => $expected) {
    if ($router->url($name) !== $expected) {
        fwrite(
            STDERR,
            "Маршрут {$name} зарегистрирован неверно.\n",
        );
        exit(1);
    }
}

$forgotHtml = ThemeRenderer::fromConfig()->capture(
    'auth.password_forgot',
    [
        'submitted' => false,
        'error' => null,
    ],
);
$resetHtml = ThemeRenderer::fromConfig()->capture(
    'auth.password_reset',
    [
        'token' => $firstToken,
        'validToken' => true,
        'changed' => false,
        'error' => null,
    ],
);

foreach ([
    'name="email"',
    'admin/password/forgot',
] as $expected) {
    if (!str_contains($forgotHtml, $expected)) {
        fwrite(
            STDERR,
            "Forgot template не содержит {$expected}.\n",
        );
        exit(1);
    }
}

foreach ([
    'name="token"',
    'name="new_password"',
    'admin/password/reset',
] as $expected) {
    if (!str_contains($resetHtml, $expected)) {
        fwrite(
            STDERR,
            "Reset template не содержит {$expected}.\n",
        );
        exit(1);
    }
}

echo "Admin password reset smoke OK\n";
