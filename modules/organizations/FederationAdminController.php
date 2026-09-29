<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;
use Throwable;

final class FederationAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        $this->render(
            $request,
            null,
            null,
            [
                'remote_base_url' => '',
            ],
        );
    }

    public function discover(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        $baseUrl = trim(
            (string) $request->post(
                'remote_base_url',
                '',
            )
        );

        try {
            $preview = (new FederationDiscoveryClient())
                ->discover($baseUrl);

            self::assertNotSelf($preview['instance_id']);

            $this->render(
                $request,
                $preview,
                null,
                [
                    'remote_base_url' =>
                        $preview['base_url'],
                ],
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS federation discovery: '
                . $error->getMessage()
            );

            $this->render(
                $request,
                null,
                'Не удалось проверить удалённый ChurchCMS. Проверьте адрес, TLS и настройки discovery.',
                [
                    'remote_base_url' => $baseUrl,
                ],
            );
        }
    }

    public function connect(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        $baseUrl = trim(
            (string) $request->post(
                'remote_base_url',
                '',
            )
        );

        try {
            $preview = (new FederationDiscoveryClient())
                ->discover($baseUrl);

            self::assertNotSelf($preview['instance_id']);

            $linkId = FederationService::fromDatabase()
                ->connect(
                    localOrganizationPublicId: trim(
                        (string) $request->post(
                            'local_organization_public_id',
                            '',
                        )
                    ),
                    relation: trim(
                        (string) $request->post(
                            'relation',
                            '',
                        )
                    ),
                    remoteInstanceId:
                        $preview['instance_id'],
                    remoteOrganizationPublicId:
                        $preview['organization']['id'],
                    remoteBaseUrl:
                        $preview['base_url'],
                    inboundScopes: self::scopes(
                        $request->post(
                            'inbound_scopes',
                            '',
                        )
                    ),
                    outboundScopes: self::scopes(
                        $request->post(
                            'outbound_scopes',
                            '',
                        )
                    ),
                    outboundToken: self::optional(
                        $request->post(
                            'outbound_token',
                            null,
                        )
                    ),
                    remoteProfile:
                        $preview['profile'],
                    remoteName:
                        $preview['organization']['name'],
                );

            AuditLog::emit(
                eventType: 'federation.link.connected',
                actorUserId: self::actorId($request),
                subjectType: 'federation_link',
                subjectId: $linkId,
                metadata: [
                    'remote_instance_id' =>
                        $preview['instance_id'],
                    'relation' => trim(
                        (string) $request->post(
                            'relation',
                            '',
                        )
                    ),
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/federation?status=connected',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS federation connect rejected: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/federation?status=connect-rejected',
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS federation connect failed: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/federation?status=connect-failed',
            );
        }
    }

    public function check(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        $repository = FederationRepository::fromDatabase();
        $link = $repository->findByPublicId($publicId);

        if ($link === null || $link->status === 'revoked') {
            Response::redirectLocal(
                '/admin/federation?status=health-unavailable',
            );
        }

        try {
            $preview = (new FederationDiscoveryClient())
                ->discover($link->remoteBaseUrl);

            $health = FederationService::fromDatabase()
                ->recordHealthSuccess(
                    publicId: $publicId,
                    remoteInstanceId: $preview['instance_id'],
                    remoteOrganizationPublicId:
                        $preview['organization']['id'],
                    remoteProfile: $preview['profile'],
                    remoteName: $preview['organization']['name'],
                );

            AuditLog::emit(
                eventType: $health === 'active'
                    ? 'federation.link.health_ok'
                    : 'federation.link.health_conflict',
                actorUserId: self::actorId($request),
                subjectType: 'federation_link',
                subjectId: $publicId,
                metadata: ['health' => $health],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/federation?status='
                . ($health === 'active'
                    ? 'health-ok'
                    : 'health-conflict'),
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS federation health check failed: '
                . $error->getMessage()
            );

            try {
                FederationService::fromDatabase()
                    ->recordHealthFailure($publicId);

                AuditLog::emit(
                    eventType: 'federation.link.health_failed',
                    actorUserId: self::actorId($request),
                    subjectType: 'federation_link',
                    subjectId: $publicId,
                    request: $request,
                );
            } catch (Throwable $storageError) {
                error_log(
                    'ChurchCMS federation health state failed: '
                    . $storageError->getMessage()
                );
            }

            Response::redirectLocal(
                '/admin/federation?status=health-failed',
            );
        }
    }

    public function revoke(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        if (
            trim(
                (string) $request->post(
                    'confirm_link',
                    '',
                )
            ) !== $publicId
        ) {
            Response::redirectLocal(
                '/admin/federation?status=revoke-confirm-required',
            );
        }

        try {
            FederationService::fromDatabase()
                ->revoke($publicId);

            AuditLog::emit(
                eventType: 'federation.link.revoked',
                actorUserId: self::actorId($request),
                subjectType: 'federation_link',
                subjectId: $publicId,
                request: $request,
            );

            Response::redirectLocal(
                '/admin/federation?status=revoked',
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS federation revoke failed: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/federation?status=revoke-failed',
            );
        }
    }

    /**
     * @param array{
     *     base_url:string,
     *     instance_id:string,
     *     site_key:string,
     *     profile:string,
     *     organization:array{id:string,type:string,name:string},
     *     capabilities:list<string>
     * }|null $preview
     * @param array<string,mixed> $form
     */
    private function render(
        Request $request,
        ?array $preview,
        ?string $error,
        array $form,
    ): never {
        $organizations = array_values(array_filter(
            OrganizationRepository::fromDatabase()
                ->tree(),
            static fn(OrganizationUnit $unit): bool =>
                $unit->status === 'active',
        ));

        AdminShell::page(
            $request,
            'admin.federation.index',
            [
                'title' => 'Связи ChurchCMS',
                'links' => FederationRepository::fromDatabase()
                    ->links(),
                'organizations' => $organizations,
                'preview' => $preview,
                'federationError' => $error,
                'federationForm' => $form,
                'federationStatus' => self::status(
                    $request,
                ),
                'syncDashboard' =>
                    FederationSyncDashboardService::fromDatabase()
                        ->snapshot(),
            ],
            'federation',
        );
    }

    private static function assertNotSelf(
        string $remoteInstanceId,
    ): void {
        $localInstanceId = trim(
            (string) Config::get(
                'federation.instance_id',
                '',
            )
        );

        if (
            $localInstanceId !== ''
            && hash_equals(
                strtolower($localInstanceId),
                strtolower($remoteInstanceId),
            )
        ) {
            throw new InvalidArgumentException(
                'Нельзя связать ChurchCMS с самим собой.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function scopes(mixed $value): array
    {
        $parts = preg_split(
            '/[\s,;]+/',
            trim((string) ($value ?? '')),
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        if (!is_array($parts)) {
            return [];
        }

        $scopes = [];
        foreach ($parts as $scope) {
            $scope = trim($scope);
            if ($scope !== '') {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    private static function optional(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private static function actorId(
        Request $request,
    ): ?int {
        $user = $request->attribute('admin.user');
        $id = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array{kind:string,title:string,message:string}|null
     */
    private static function status(
        Request $request,
    ): ?array {
        return match (
            (string) $request->get('status', '')
        ) {
            'connected' => [
                'kind' => 'success',
                'title' => 'Связь создана',
                'message' => 'Удалённый узел повторно проверен, параметры доверия сохранены.',
            ],
            'revoked' => [
                'kind' => 'success',
                'title' => 'Доверие отозвано',
                'message' => 'Credential удалён, связь сохранена в истории со статусом revoked.',
            ],
            'health-ok' => [
                'kind' => 'success',
                'title' => 'Связь доступна',
                'message' => 'Discovery подтверждает прежнюю identity удалённого ChurchCMS.',
            ],
            'health-conflict' => [
                'kind' => 'error',
                'title' => 'Конфликт identity',
                'message' => 'По сохранённому адресу ответил другой ChurchCMS-узел. Credential и scopes не изменены.',
            ],
            'health-failed' => [
                'kind' => 'error',
                'title' => 'Связь недоступна',
                'message' => 'Повторная проверка не выполнена. Можно безопасно запустить её ещё раз.',
            ],
            'health-unavailable' => [
                'kind' => 'error',
                'title' => 'Проверка недоступна',
                'message' => 'Связь не найдена или доверие уже отозвано.',
            ],
            'connect-rejected' => [
                'kind' => 'error',
                'title' => 'Связь отклонена',
                'message' => 'Параметры связи не прошли проверку безопасности.',
            ],
            'connect-failed' => [
                'kind' => 'error',
                'title' => 'Связь не создана',
                'message' => 'Не удалось повторно проверить узел или сохранить связь.',
            ],
            'revoke-confirm-required' => [
                'kind' => 'error',
                'title' => 'Нужно подтверждение',
                'message' => 'Отзыв доверия требует явного подтверждения выбранной связи.',
            ],
            'revoke-failed' => [
                'kind' => 'error',
                'title' => 'Доверие не отозвано',
                'message' => 'Операция не выполнена. Подробность записана в журнал сервера.',
            ],
            default => null,
        };
    }
}
