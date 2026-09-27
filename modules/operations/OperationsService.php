<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Operations;

use ChurchCMS\Core\BackupManager;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\InstallationHealthCheck;
use ChurchCMS\Core\UpdatePackageApplier;
use ChurchCMS\Core\UpdatePackageStager;

final class OperationsService
{
    private DatabaseManager $database;
    private string $root;
    private string $backupRoot;
    private string $stagingRoot;

    public function __construct(
        ?DatabaseManager $database = null,
        ?string $root = null,
        ?string $backupRoot = null,
        ?string $stagingRoot = null,
    ) {
        $this->database = $database ?? DatabaseManager::getInstance();
        $this->root = $root ?? CHURCHCMS_ROOT;
        $this->backupRoot = $backupRoot
            ?? (string) Config::get('operations.backup_path', '');
        $this->stagingRoot = $stagingRoot
            ?? (string) Config::get('operations.update_staging_path', '');
    }

    /**
     * @return array{
     *     health:array{ready:bool,checks:array<string,bool>},
     *     backups:list<array{
     *         id:string,created_at:string,app_version:string,files:int,tables:int
     *     }>,
     *     packages:list<array{
     *         id:string,version:string,files:int,deleted_files:int,
     *         code_only:bool,ready:bool
     *     }>
     * }
     */
    public function overview(): array
    {
        return [
            'health' => (new InstallationHealthCheck(
                $this->database,
                $this->root,
            ))->check(),
            'backups' => $this->backupManager()->backups(20),
            'packages' => $this->stager()->packages(20),
        ];
    }

    /**
     * @return array{id:string,files:int,tables:int}
     */
    public function createBackup(): array
    {
        return $this->backupManager()->create();
    }

    public function verifyBackup(string $backupId): void
    {
        $this->backupManager()->verify($backupId);
    }

    /**
     * @return array{
     *     id:string,version:string,backup_id:string,files:int,deleted_files:int
     * }
     */
    public function applyUpdate(string $stageId): array
    {
        return (new UpdatePackageApplier(
            $this->database,
            $this->root,
            $this->stagingRoot,
            $this->backupRoot,
        ))->apply($stageId);
    }

    private function backupManager(): BackupManager
    {
        return new BackupManager(
            $this->database,
            $this->root,
            $this->backupRoot,
        );
    }

    private function stager(): UpdatePackageStager
    {
        return new UpdatePackageStager(
            $this->root,
            $this->stagingRoot,
        );
    }
}
