<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\ModuleRuntimeLoader;
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
                'inboxItems' => ExternalChannelItemRepository::fromDatabase()->pending(),
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

    public function ignoreInboxItem(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'social.manage',
        );

        $repository = ExternalChannelItemRepository::fromDatabase();
        $item = $repository->findByPublicId($publicId);
        if ($item === null || $item->status !== 'pending') {
            Response::text('404 Not Found', 404);
        }

        $repository->markIgnored($publicId);

        AuditLog::emit(
            eventType: 'external_channel.inbox.ignored',
            actorUserId: self::actorId($request),
            subjectType: 'external_channel_item',
            subjectId: $publicId,
            metadata: [
                'connection_id' => $item->connectionId,
                'remote_id' => $item->remoteId,
            ],
            request: $request,
        );

        Response::redirectLocal(
            '/admin/external-channels?status=inbox-ignored',
        );
    }

    public function linkInboxItem(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'social.manage',
        );

        $repository = ExternalChannelItemRepository::fromDatabase();
        $item = $repository->findByPublicId($publicId);
        if ($item === null || $item->status !== 'pending') {
            Response::text('404 Not Found', 404);
        }

        $publicationPublicId = trim(
            (string) $request->post('publication_public_id', '')
        );
        if ($publicationPublicId === '') {
            Response::redirectLocal(
                '/admin/external-channels?status=link-invalid',
            );
        }

        $capability = ModuleRuntimeLoader::capability(
            'publications',
            'publications.repository',
        );

        if (
            $capability === null
            || !method_exists($capability, 'repository')
        ) {
            Response::redirectLocal(
                '/admin/external-channels?status=link-unavailable',
            );
        }

        $publicationRepository = $capability->repository();
        if (!method_exists($publicationRepository, 'findByPublicId')) {
            Response::redirectLocal(
                '/admin/external-channels?status=link-unavailable',
            );
        }

        $publication = $publicationRepository->findByPublicId(
            $publicationPublicId
        );
        if ($publication === null) {
            Response::redirectLocal(
                '/admin/external-channels?status=link-invalid',
            );
        }

        $repository->linkToPublication(
            $publicId,
            $publication->id,
        );

        AuditLog::emit(
            eventType: 'external_channel.inbox.linked',
            actorUserId: self::actorId($request),
            subjectType: 'external_channel_item',
            subjectId: $publicId,
            metadata: [
                'connection_id' => $item->connectionId,
                'remote_id' => $item->remoteId,
                'publication_public_id' => $publicationPublicId,
            ],
            request: $request,
        );

        Response::redirectLocal(
            '/admin/external-channels?status=inbox-linked',
        );
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
            'inbox-ignored' => [
                'kind' => 'success',
                'title' => 'Входящий материал скрыт',
                'message' => 'Объект исключён из очереди проверки и не был импортирован.',
            ],
            'inbox-linked' => [
                'kind' => 'success',
                'title' => 'Материал связан',
                'message' => 'Внешний объект связан с существующей публикацией ChurchCMS.',
            ],
            'link-invalid' => [
                'kind' => 'error',
                'title' => 'Публикация не найдена',
                'message' => 'Укажите корректный публичный ID существующей публикации.',
            ],
            'link-unavailable' => [
                'kind' => 'error',
                'title' => 'Связь временно недоступна',
                'message' => 'Модуль публикаций сейчас не предоставляет нужную возможность.',
            ],
            default => null,
        };
    }
}
