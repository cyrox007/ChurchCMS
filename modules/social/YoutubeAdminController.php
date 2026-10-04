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

final class YoutubeAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'social.manage',
        );

        $connections = array_values(array_filter(
            SocialConnectionRepository::fromDatabase()->all(),
            static fn(SocialConnection $connection): bool =>
                $connection->provider === 'youtube',
        ));

        AdminShell::page(
            $request,
            'admin.youtube-channel',
            [
                'title' => 'YouTube',
                'connections' => $connections,
                'channelStatus' => self::status($request),
            ],
            'youtube-channel',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'social.manage',
        );

        try {
            $connection = YoutubeAdminConnectionCreator::fromDatabase()
                ->create([
                    'name' => $request->post('name', ''),
                    'target_ref' => $request->post('target_ref', ''),
                    'api_key' => $request->post('api_key', ''),
                    'access_token' => $request->post('access_token', ''),
                    'refresh_token' => $request->post('refresh_token', ''),
                    'client_id' => $request->post('client_id', ''),
                    'client_secret' => $request->post('client_secret', ''),
                    'privacy' => $request->post('privacy', 'unlisted'),
                    'category_id' => $request->post('category_id', '22'),
                    'outbound_enabled' =>
                        $request->post('outbound_enabled', ''),
                    'inbound_enabled' =>
                        $request->post('inbound_enabled', ''),
                ]);

            AuditLog::emit(
                eventType: 'external_channel.youtube.created',
                actorUserId: self::actorId($request),
                subjectType: 'social_connection',
                subjectId: $connection->publicId,
                metadata: [
                    'target_ref' => $connection->targetRef,
                    'outbound_enabled' => $connection->outboundEnabled,
                    'inbound_enabled' => $connection->inboundEnabled,
                    'privacy' => (string) (
                        $connection->settings['youtube_privacy']
                        ?? 'unlisted'
                    ),
                    'category_id' => (string) (
                        $connection->settings['youtube_category_id']
                        ?? '22'
                    ),
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/external-channels/youtube?status=created',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS YouTube: ' . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/external-channels/youtube?status=invalid',
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS YouTube: ' . $error->getMessage()
            );

            AuditLog::emit(
                eventType: 'external_channel.youtube.create_failed',
                severity: 'error',
                actorUserId: self::actorId($request),
                subjectType: 'social_connection',
                metadata: [
                    'error_class' => $error::class,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/external-channels/youtube?status=create-failed',
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
                'title' => 'YouTube подключён',
                'message' => 'Канал проверен, OAuth-секрет сохранён в зашифрованном виде.',
            ],
            'invalid' => [
                'kind' => 'error',
                'title' => 'Проверьте параметры YouTube',
                'message' => 'API key, OAuth или параметры публикации заполнены некорректно.',
            ],
            'create-failed' => [
                'kind' => 'error',
                'title' => 'YouTube не подключён',
                'message' => 'Google API не подтвердил канал или OAuth-доступ. Секрет не сохранён.',
            ],
            default => null,
        };
    }
}
