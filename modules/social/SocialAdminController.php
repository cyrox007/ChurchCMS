<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;
use Throwable;

final class SocialAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'social.manage',
        );

        $service = SocialConnectionService::fromDatabase();

        AdminShell::page(
            $request,
            'admin.external-channels',
            [
                'title' => 'Внешние каналы',
                'connections' => SocialConnectionRepository::fromDatabase()->all(),
                'adapters' => $service->availableAdapters(),
                'channelStatus' => self::status($request),
            ],
            'external-channels',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'social.manage',
        );

        try {
            $connection = SocialConnectionService::fromDatabase()->create(
                provider: (string) $request->post('provider', ''),
                name: (string) $request->post('name', ''),
                targetRef: (string) $request->post('target_ref', ''),
                credentials: (string) $request->post('credentials', ''),
                outboundEnabled:
                    (string) $request->post('outbound_enabled', '') === '1',
                inboundEnabled:
                    (string) $request->post('inbound_enabled', '') === '1',
            );

            AuditLog::emit(
                eventType: 'external_channel.connection.created',
                actorUserId: self::actorId($request),
                subjectType: 'social_connection',
                subjectId: $connection->publicId,
                metadata: [
                    'provider' => $connection->provider,
                    'target_ref' => $connection->targetRef,
                    'outbound_enabled' => $connection->outboundEnabled,
                    'inbound_enabled' => $connection->inboundEnabled,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/external-channels?status=created',
            );
        } catch (InvalidArgumentException $e) {
            error_log(
                'ChurchCMS внешние каналы: '
                . $e->getMessage()
            );

            Response::redirectLocal(
                '/admin/external-channels?status=invalid',
            );
        } catch (Throwable $e) {
            error_log(
                'ChurchCMS внешние каналы: '
                . $e->getMessage()
            );

            AuditLog::emit(
                eventType: 'external_channel.connection.create_failed',
                severity: 'error',
                actorUserId: self::actorId($request),
                subjectType: 'social_connection',
                metadata: [
                    'provider' => (string) $request->post('provider', ''),
                    'error_class' => $e::class,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/external-channels?status=create-failed',
            );
        }
    }

    private static function actorId(Request $request): ?int
    {
        $user = $request->attribute('admin.user');
        $id = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array{kind:string,title:string,message:string}|null
     */
    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => [
                'kind' => 'success',
                'title' => 'Канал подключён',
                'message' => 'Проверка адаптера пройдена, секрет сохранён в зашифрованном виде.',
            ],
            'invalid' => [
                'kind' => 'error',
                'title' => 'Проверьте параметры',
                'message' => 'Не удалось создать подключение: одно или несколько полей заполнены некорректно.',
            ],
            'create-failed' => [
                'kind' => 'error',
                'title' => 'Подключение не создано',
                'message' => 'Проверка канала не пройдена или адаптер временно недоступен. Секрет не сохранён.',
            ],
            default => null,
        };
    }
}
