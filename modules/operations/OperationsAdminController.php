<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Operations;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use Throwable;

final class OperationsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        $overview = (new OperationsService())->overview();

        AdminShell::page(
            $request,
            'admin.operations',
            [
                'title' => 'Система',
                'health' => $overview['health'],
                'backups' => $overview['backups'],
                'packages' => $overview['packages'],
                'operationWarnings' => $overview['warnings'],
                'operationStatus' => self::status($request),
            ],
            'operations',
        );
    }

    public function createBackup(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        try {
            $result = (new OperationsService())->createBackup();

            AuditLog::emit(
                eventType: 'operations.backup.created',
                actorUserId: self::actorId($request),
                subjectType: 'backup',
                subjectId: $result['id'],
                metadata: [
                    'files' => $result['files'],
                    'tables' => $result['tables'],
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/system?status=backup-created',
            );
        } catch (Throwable $e) {
            self::failed(
                $request,
                'operations.backup.create_failed',
                'backup-create-failed',
                $e,
            );
        }
    }

    public function verifyBackup(
        Request $request,
        string $backupId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        try {
            (new OperationsService())->verifyBackup($backupId);

            AuditLog::emit(
                eventType: 'operations.backup.verified',
                actorUserId: self::actorId($request),
                subjectType: 'backup',
                subjectId: $backupId,
                request: $request,
            );

            Response::redirectLocal(
                '/admin/system?status=backup-verified',
            );
        } catch (Throwable $e) {
            self::failed(
                $request,
                'operations.backup.verify_failed',
                'backup-verify-failed',
                $e,
                'backup',
                $backupId,
            );
        }
    }

    public function restoreBackup(
        Request $request,
        string $backupId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        if (
            (string) $request->post('confirm_restore', '')
            !== $backupId
        ) {
            Response::redirectLocal(
                '/admin/system?status=restore-confirm-required',
            );
        }

        try {
            $result = (new OperationsService())
                ->restoreBackup($backupId);

            AuditLog::emit(
                eventType: 'operations.backup.restored',
                actorUserId: self::actorId($request),
                subjectType: 'backup',
                subjectId: $backupId,
                metadata: [
                    'safety_backup_id' => $result['safety_backup_id'],
                    'tables' => $result['tables'],
                    'rows' => $result['rows'],
                    'files' => $result['files'],
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/system?status=backup-restored',
            );
        } catch (Throwable $e) {
            self::failed(
                $request,
                'operations.backup.restore_failed',
                'backup-restore-failed',
                $e,
                'backup',
                $backupId,
            );
        }
    }

    public function applyUpdate(
        Request $request,
        string $stageId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'settings.manage',
        );

        if (
            (string) $request->post('confirm_stage', '')
            !== $stageId
        ) {
            Response::redirectLocal(
                '/admin/system?status=update-confirm-required',
            );
        }

        try {
            $result = (new OperationsService())
                ->applyUpdate($stageId);

            AuditLog::emit(
                eventType: 'operations.update.applied',
                actorUserId: self::actorId($request),
                subjectType: 'update_stage',
                subjectId: $stageId,
                metadata: [
                    'version' => $result['version'],
                    'backup_id' => $result['backup_id'],
                    'files' => $result['files'],
                    'deleted_files' => $result['deleted_files'],
                    'signature_key_id' =>
                        $result['signature_key_id'],
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/system?status=update-applied',
            );
        } catch (Throwable $e) {
            self::failed(
                $request,
                'operations.update.apply_failed',
                'update-apply-failed',
                $e,
                'update_stage',
                $stageId,
            );
        }
    }

    private static function failed(
        Request $request,
        string $eventType,
        string $status,
        Throwable $error,
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): never {
        error_log(
            'ChurchCMS операция админки: '
            . $error->getMessage()
        );

        AuditLog::emit(
            eventType: $eventType,
            severity: 'error',
            actorUserId: self::actorId($request),
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: [
                'error_class' => $error::class,
            ],
            request: $request,
        );

        Response::redirectLocal(
            '/admin/system?status=' . $status,
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
            'backup-created' => [
                'kind' => 'success',
                'title' => 'Резервная копия создана',
                'message' => 'Новая копия создана и автоматически проверена.',
            ],
            'backup-verified' => [
                'kind' => 'success',
                'title' => 'Резервная копия проверена',
                'message' => 'Манифест и контрольные суммы совпадают.',
            ],
            'backup-restored' => [
                'kind' => 'success',
                'title' => 'Резервная копия восстановлена',
                'message' => 'База данных, локальная конфигурация и uploads восстановлены. Перед операцией создан аварийный снимок.',
            ],
            'restore-confirm-required' => [
                'kind' => 'error',
                'title' => 'Нужно подтверждение восстановления',
                'message' => 'Подтвердите замену БД, локальной конфигурации и uploads выбранной резервной копией.',
            ],
            'update-applied' => [
                'kind' => 'success',
                'title' => 'Обновление применено',
                'message' => 'Файлы обновлены, резервная копия создана, healthcheck пройден.',
            ],
            'update-confirm-required' => [
                'kind' => 'error',
                'title' => 'Нужно подтверждение',
                'message' => 'Перед применением обновления отметьте явное подтверждение выбранного пакета.',
            ],
            'backup-create-failed' => [
                'kind' => 'error',
                'title' => 'Копию создать не удалось',
                'message' => 'Проверьте состояние системы и повторите операцию. Подробность записана в журнал сервера.',
            ],
            'backup-verify-failed' => [
                'kind' => 'error',
                'title' => 'Проверка копии не пройдена',
                'message' => 'Эту резервную копию нельзя считать готовой к восстановлению.',
            ],
            'backup-restore-failed' => [
                'kind' => 'error',
                'title' => 'Восстановление не завершено',
                'message' => 'ChurchCMS остановила операцию и попыталась вернуть аварийную копию. Подробность записана в журнал сервера.',
            ],
            'update-apply-failed' => [
                'kind' => 'error',
                'title' => 'Обновление не применено',
                'message' => 'ChurchCMS остановила операцию. Если замена файлов уже началась, выполнен автоматический rollback.',
            ],
            default => null,
        };
    }
}
