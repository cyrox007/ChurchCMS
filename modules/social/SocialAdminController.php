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
        $publicationCapability = ModuleRuntimeLoader::capability(
            'publications',
            'publications.external-import',
        );
        $userId = self::actorId($request) ?? 0;
        $canImportExternal = $userId > 0
            && AdminAuthorization::can(
                $request,
                'publications.create',
            )
            && $publicationCapability !== null
            && method_exists(
                $publicationCapability,
                'externalImportOwners',
            );
        $importOwners = $canImportExternal
            ? $publicationCapability->externalImportOwners(
                $userId,
            )
            : [];

        AdminShell::page(
            $request,
            'admin.external-channels',
            [
                'title' => 'Внешние каналы',
                'connections' => SocialConnectionRepository::fromDatabase()->all(),
                'adapters' => $service->availableAdapters(),
                'inboxItems' => ExternalChannelItemRepository::fromDatabase()->pending(),
                'canImportExternal' => $canImportExternal,
                'importOwners' => $importOwners,
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
        AdminAuthorization::requirePermission(
            $request,
            'publications.read',
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
            'publications.external-import',
        );
        if (
            $capability === null
            || !method_exists(
                $capability,
                'findLinkablePublication',
            )
        ) {
            Response::redirectLocal(
                '/admin/external-channels?status=link-unavailable',
            );
        }

        $userId = self::actorId($request) ?? 0;
        $publication = $capability->findLinkablePublication(
            $userId,
            $publicationPublicId,
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
            actorUserId: $userId,
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

    public function importInboxItem(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'social.manage',
        );
        AdminAuthorization::requirePermission(
            $request,
            'publications.create',
        );

        $repository = ExternalChannelItemRepository::fromDatabase();
        $item = $repository->findByPublicId($publicId);
        if ($item === null || $item->status !== 'pending') {
            Response::text('404 Not Found', 404);
        }

        $ownerPublicId = trim(
            (string) $request->post(
                'owner_organization_public_id',
                '',
            )
        );
        if ($ownerPublicId === '') {
            Response::redirectLocal(
                '/admin/external-channels?status=import-invalid',
            );
        }

        $capability = ModuleRuntimeLoader::capability(
            'publications',
            'publications.external-import',
        );
        if (
            $capability === null
            || !method_exists(
                $capability,
                'importExternalDraft',
            )
        ) {
            Response::redirectLocal(
                '/admin/external-channels?status=import-unavailable',
            );
        }

        try {
            $userId = self::actorId($request) ?? 0;
            $draft = $capability->importExternalDraft(
                userId: $userId,
                ownerPublicId: $ownerPublicId,
                title: $item->title ?? '',
                bodyText: $item->bodyText,
                kind: $item->kind,
                canonicalUrl: $item->canonicalUrl,
            );

            $repository->linkToPublication(
                $publicId,
                (int) $draft['id'],
            );

            AuditLog::emit(
                eventType: 'external_channel.inbox.imported',
                actorUserId: $userId,
                subjectType: 'external_channel_item',
                subjectId: $publicId,
                metadata: [
                    'connection_id' => $item->connectionId,
                    'remote_id' => $item->remoteId,
                    'publication_public_id' =>
                        (string) $draft['public_id'],
                    'owner_organization_public_id' =>
                        $ownerPublicId,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/publications/'
                . rawurlencode(
                    (string) $draft['public_id']
                )
                . '?saved=1'
            );
        } catch (InvalidArgumentException $e) {
            error_log(
                'ChurchCMS импорт внешнего материала: '
                . $e->getMessage()
            );

            Response::redirectLocal(
                '/admin/external-channels?status=import-invalid',
            );
        } catch (Throwable $e) {
            error_log(
                'ChurchCMS импорт внешнего материала: '
                . $e->getMessage()
            );

            AuditLog::emit(
                eventType: 'external_channel.inbox.import_failed',
                severity: 'error',
                actorUserId: self::actorId($request),
                subjectType: 'external_channel_item',
                subjectId: $publicId,
                metadata: [
                    'error_class' => $e::class,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/external-channels?status=import-failed',
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
            'import-invalid' => [
                'kind' => 'error',
                'title' => 'Черновик не создан',
                'message' => 'Выберите доступную организацию-владельца и повторите импорт.',
            ],
            'import-unavailable' => [
                'kind' => 'error',
                'title' => 'Импорт временно недоступен',
                'message' => 'Модуль публикаций сейчас не предоставляет безопасный импорт.',
            ],
            'import-failed' => [
                'kind' => 'error',
                'title' => 'Импорт не завершён',
                'message' => 'Внешний материал остался в очереди. Подробность записана в журнал сервера.',
            ],
            default => null,
        };
    }
}
