<?php

declare(strict_types=1);

use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookDeliveryRepository;
use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookEndpointRepository;
use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookHttpClient;
use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookSignature;
use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookTransport;
use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookWorker;

require dirname(__DIR__) . '/core.php';

function failWebhookSmoke(string $message): never
{
    fwrite(STDERR, "Ошибка smoke исходящих webhooks: {$message}\n");
    exit(1);
}

final class FakeWebhookTransport implements DistributionWebhookTransport
{
    /** @var list<int> */
    public array $statuses = [503, 204];

    /** @var list<array{url:string,body:string,headers:array<string,string>}> */
    public array $requests = [];

    public function post(string $url, string $body, array $headers): array
    {
        $this->requests[] = [
            'url' => $url,
            'body' => $body,
            'headers' => $headers,
        ];
        $status = array_shift($this->statuses) ?? 204;

        return ['status' => $status, 'body' => ''];
    }
}

$pdo = DatabaseManager::getInstance()->connection();
$siteKey = 'default';
$publicationId = '42f5e0c2-0c98-4f11-9ea3-eac8562dd5e5';
$now = gmdate('Y-m-d H:i:s');

$pdo->prepare('DELETE FROM publications WHERE public_id = :public_id')
    ->execute([':public_id' => $publicationId]);
$pdo->exec('DELETE FROM distribution_webhook_deliveries');
$pdo->exec('DELETE FROM distribution_webhook_endpoints');

$statement = $pdo->prepare(
    'INSERT INTO publications '
    . '(public_id, site_key, type, status, slug, title, excerpt, body_html, author_name, '
    . 'published_at, created_at, updated_at, syndication_targets, syndication_title, syndication_excerpt, comments_enabled) '
    . 'VALUES (:public_id, :site_key, :type, :status, :slug, :title, :excerpt, :body_html, NULL, '
    . ':published_at, :created_at, :updated_at, :targets, NULL, NULL, 0)'
);
$statement->execute([
    ':public_id' => $publicationId,
    ':site_key' => $siteKey,
    ':type' => 'news',
    ':status' => 'published',
    ':slug' => 'webhook-smoke',
    ':title' => 'Webhook smoke',
    ':excerpt' => 'Краткое описание',
    ':body_html' => '<p>Полный текст</p>',
    ':published_at' => $now,
    ':created_at' => $now,
    ':updated_at' => $now,
    ':targets' => '[]',
]);

$secret = 'smoke-secret-0123456789-abcdef';
$endpoints = DistributionWebhookEndpointRepository::fromDatabase();
$endpointPublicId = $endpoints->create(
    $siteKey,
    'Smoke endpoint',
    'https://example.com/churchcms-hook',
    $secret,
    ['publication.published', 'publication.withdrawn'],
);

$encrypted = $pdo->query(
    'SELECT secret_encrypted FROM distribution_webhook_endpoints LIMIT 1'
)->fetchColumn();
if (!is_string($encrypted) || $encrypted === '' || str_contains($encrypted, $secret)) {
    failWebhookSmoke('secret не зашифрован в БД');
}

foreach (['distribution_webhooks.read', 'distribution_webhooks.manage'] as $permission) {
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM permissions WHERE permission_key = :permission_key'
    );
    $check->execute([':permission_key' => $permission]);
    if ((int) $check->fetchColumn() !== 1) {
        failWebhookSmoke('не зарегистрировано право ' . $permission);
    }
}

AuditLog::emit(
    eventType: 'publication.published',
    subjectType: 'publication',
    subjectId: $publicationId,
);

$deliveries = DistributionWebhookDeliveryRepository::fromDatabase();
$due = $deliveries->due(10);
if (count($due) !== 1) {
    failWebhookSmoke('событие публикации не попало в очередь');
}
$decoded = json_decode((string) $due[0]['payload_json'], true, 32, JSON_THROW_ON_ERROR);
if (($decoded['data']['public_id'] ?? null) !== $publicationId) {
    failWebhookSmoke('payload не содержит public_id');
}
if (array_key_exists('body_html', $decoded['data'] ?? [])) {
    failWebhookSmoke('payload не должен содержать body_html');
}

$transport = new FakeWebhookTransport();
$worker = new DistributionWebhookWorker($deliveries, $transport);
$first = $worker->run(10);
if ($first['retried'] !== 1 || count($transport->requests) !== 1) {
    failWebhookSmoke('503 не перевёл доставку в retry');
}

$request = $transport->requests[0];
$timestamp = $request['headers']['X-ChurchCMS-Timestamp'] ?? '';
$signature = $request['headers']['X-ChurchCMS-Signature'] ?? '';
if (
    $timestamp === ''
    || !hash_equals(
        DistributionWebhookSignature::sign($secret, $timestamp, $request['body']),
        $signature,
    )
) {
    failWebhookSmoke('HMAC-подпись не совпадает');
}
if (($request['headers']['X-ChurchCMS-Event'] ?? '') !== 'publication.published') {
    failWebhookSmoke('не передан тип события');
}

$pdo->exec(
    "UPDATE distribution_webhook_deliveries SET next_attempt_at = CURRENT_TIMESTAMP WHERE status = 'retry'"
);
$second = $worker->run(10);
if ($second['delivered'] !== 1) {
    failWebhookSmoke('повторная доставка не завершилась успешно');
}

$recent = $deliveries->recent(10);
if (($recent[0]['status'] ?? '') !== 'delivered' || (int) ($recent[0]['attempt_count'] ?? 0) !== 2) {
    failWebhookSmoke('неверное финальное состояние доставки');
}

$endpoints->setActive($endpointPublicId, false, $siteKey);
AuditLog::emit(
    eventType: 'publication.withdrawn',
    subjectType: 'publication',
    subjectId: $publicationId,
);
if (count($deliveries->recent(10)) !== 1) {
    failWebhookSmoke('отключённый endpoint получил новое событие');
}

try {
    (new DistributionWebhookHttpClient())->assertPublicHttpsEndpoint(
        'https://127.0.0.1/private',
    );
    failWebhookSmoke('локальный IP не был заблокирован');
} catch (RuntimeException) {
}

try {
    (new DistributionWebhookHttpClient())->assertPublicHttpsEndpoint(
        'http://example.com/insecure',
    );
    failWebhookSmoke('HTTP endpoint не был заблокирован');
} catch (RuntimeException) {
}

echo "Smoke исходящих webhooks пройден.\n";
