<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use Throwable;

final class DistributionWebhookAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'distribution_webhooks.read',
        );

        AdminShell::page(
            $request,
            'admin.distribution_webhooks',
            [
                'title' => 'Исходящие webhooks',
                'endpoints' => DistributionWebhookEndpointRepository::fromDatabase()
                    ->all('default'),
                'deliveries' => DistributionWebhookDeliveryRepository::fromDatabase()
                    ->recent(100),
                'canManage' => AdminAuthorization::can(
                    $request,
                    'distribution_webhooks.manage',
                ),
                'webhookStatus' => self::status($request),
            ],
            'distribution_webhooks',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'distribution_webhooks.manage',
        );

        $events = $request->post('event_types', []);
        if (!is_array($events)) {
            $events = [];
        }

        try {
            $publicId = DistributionWebhookEndpointRepository::fromDatabase()
                ->create(
                    siteKey: 'default',
                    name: (string) $request->post('name', ''),
                    endpointUrl: (string) $request->post('endpoint_url', ''),
                    secret: (string) $request->post('secret', ''),
                    eventTypes: array_values(array_filter($events, 'is_string')),
                );
            self::audit($request, 'distribution_webhook.created', $publicId);
            Response::redirectLocal('/admin/distribution-webhooks?status=created');
        } catch (Throwable $error) {
            self::log('create', $error);
            Response::redirectLocal('/admin/distribution-webhooks?status=invalid');
        }
    }

    public function enable(Request $request, string $publicId): never
    {
        self::setActive($request, $publicId, true);
    }

    public function disable(Request $request, string $publicId): never
    {
        self::setActive($request, $publicId, false);
    }

    private static function setActive(
        Request $request,
        string $publicId,
        bool $active,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'distribution_webhooks.manage',
        );

        try {
            DistributionWebhookEndpointRepository::fromDatabase()->setActive(
                trim($publicId),
                $active,
                'default',
            );
            self::audit(
                $request,
                $active
                    ? 'distribution_webhook.enabled'
                    : 'distribution_webhook.disabled',
                trim($publicId),
            );
            Response::redirectLocal(
                '/admin/distribution-webhooks?status=' . ($active ? 'enabled' : 'disabled'),
            );
        } catch (Throwable $error) {
            self::log('toggle', $error);
            Response::redirectLocal('/admin/distribution-webhooks?status=invalid');
        }
    }

    private static function audit(
        Request $request,
        string $eventType,
        string $subjectId,
    ): void {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: self::userId($request),
            subjectType: 'distribution_webhook_endpoint',
            subjectId: $subjectId,
            request: $request,
        );
    }

    private static function userId(Request $request): int
    {
        $user = $request->attribute('admin.user');
        $id = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        if ($id <= 0) {
            Response::text('403 Forbidden', 403);
        }

        return $id;
    }

    private static function log(string $operation, Throwable $error): void
    {
        error_log(
            'ChurchCMS distribution webhook ' . $operation . ': ' . $error->getMessage(),
        );
    }

    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => [
                'kind' => 'success',
                'title' => 'Webhook создан',
                'message' => 'Endpoint включён и готов принимать события.',
            ],
            'enabled' => [
                'kind' => 'success',
                'title' => 'Webhook включён',
                'message' => 'Новые события снова будут ставиться в очередь.',
            ],
            'disabled' => [
                'kind' => 'success',
                'title' => 'Webhook отключён',
                'message' => 'Новые события для этого endpoint не создаются.',
            ],
            'invalid' => [
                'kind' => 'error',
                'title' => 'Изменения не сохранены',
                'message' => 'Проверьте HTTPS-адрес, secret и выбранные события.',
            ],
            default => null,
        };
    }
}
