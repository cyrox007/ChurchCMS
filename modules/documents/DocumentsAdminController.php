<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;
use Throwable;

final class DocumentsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'documents.read',
        );

        $userId = self::userId($request);
        $access = DocumentOrganizationAccessService::fromDatabase();
        $ownerIds = $access->visibleOwnerPublicIds(
            $userId,
            'documents.read',
        );
        $canCreate = AdminAuthorization::can(
            $request,
            'documents.create',
        );
        $canEdit = AdminAuthorization::can(
            $request,
            'documents.edit',
        );
        $canPublish = AdminAuthorization::can(
            $request,
            'documents.publish',
        );

        $media = DocumentMediaService::fromDatabase();
        $categories = DocumentCategoryService::fromDatabase();
        $versions = DocumentFileVersionService::fromDatabase();
        $documentRows = [];

        foreach (
            DocumentRepository::fromDatabase()
                ->adminList($ownerIds)
            as $document
        ) {
            $documentRows[] = [
                'document' => $document,
                'media_public_id' =>
                    $media->fileMediaPublicId(
                        $document->publicId,
                        $document->siteKey,
                    ),
                'category_names' =>
                    DocumentCategoryService::names(
                        $categories->forDocument(
                            $document->id,
                        ),
                    ),
                'file_versions' =>
                    $versions->forDocument(
                        $document->publicId,
                        $document->siteKey,
                        50,
                    ),
            ];
        }

        $fileOwnerIds = $canEdit
            ? $access->visibleOwnerPublicIds(
                $userId,
                'documents.edit',
            )
            : $ownerIds;

        AdminShell::page(
            $request,
            'admin.documents',
            [
                'title' => 'Документы',
                'documentRows' => $documentRows,
                'createOrganizationUnits' => $canCreate
                    ? $access->availableOwners(
                        $userId,
                        'documents.create',
                    )
                    : [],
                'editOrganizationUnits' => $canEdit
                    ? $access->availableOwners(
                        $userId,
                        'documents.edit',
                    )
                    : [],
                'defaultOwnerPublicId' =>
                    $access->defaultOwnerPublicId(
                        $userId,
                        'documents.create',
                    ),
                'availableFiles' =>
                    $media->availableFiles(
                        $fileOwnerIds,
                    ),
                'canCreate' => $canCreate,
                'canEdit' => $canEdit,
                'canPublish' => $canPublish,
                'documentStatus' => self::status($request),
            ],
            'documents',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'documents.create',
        );

        $userId = self::userId($request);
        $access = DocumentOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'documents.create',
            (string) $request->post(
                'owner_organization_public_id',
                '',
            ),
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        $pdo = DatabaseManager::getInstance()->connection();

        try {
            $pdo->beginTransaction();

            $publicId = DocumentService::fromDatabase()
                ->createDraft(
                    title: (string) $request->post('title', ''),
                    ownerOrganizationPublicId: $owner->publicId,
                    documentType: (string) $request->post(
                        'document_type',
                        'document',
                    ),
                    documentNumber: self::nullable(
                        $request->post('document_number', null),
                    ),
                    issuedOn: self::nullable(
                        $request->post('issued_on', null),
                    ),
                    summary: (string) $request->post(
                        'summary',
                        '',
                    ),
                    categoryNames:
                        DocumentCategoryService::fromInput(
                            (string) $request->post(
                                'categories',
                                '',
                            ),
                        ),
                );

            $this->saveFile(
                $request,
                $publicId,
                $access->visibleOwnerPublicIds(
                    $userId,
                    'documents.create',
                ),
            );

            $pdo->commit();

            AuditLog::emit(
                eventType: 'document.created',
                actorUserId: $userId,
                subjectType: 'document',
                subjectId: $publicId,
                request: $request,
            );

            Response::redirectLocal(
                '/admin/documents?status=created',
            );
        } catch (InvalidArgumentException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'ChurchCMS document create: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/documents?status=invalid',
            );
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    public function update(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'documents.edit',
        );

        $userId = self::userId($request);
        $access = DocumentOrganizationAccessService::fromDatabase();
        $document = self::document($publicId);

        if (!$access->canAccess(
            $userId,
            'documents.edit',
            $document,
        )) {
            Response::text('403 Forbidden', 403);
        }

        $pdo = DatabaseManager::getInstance()->connection();

        try {
            $pdo->beginTransaction();

            $service = DocumentService::fromDatabase();
            $service->updateDetails(
                $document->publicId,
                (string) $request->post('title', ''),
                (string) $request->post(
                    'document_type',
                    'document',
                ),
                self::nullable(
                    $request->post('document_number', null),
                ),
                self::nullable(
                    $request->post('issued_on', null),
                ),
                (string) $request->post('summary', ''),
                $document->siteKey,
                DocumentCategoryService::fromInput(
                    (string) $request->post(
                        'categories',
                        '',
                    ),
                ),
            );

            $owner = $access->assignableOwner(
                $userId,
                'documents.edit',
                (string) $request->post(
                    'owner_organization_public_id',
                    '',
                ),
                $document->siteKey,
            );

            if ($owner === null) {
                throw new InvalidArgumentException(
                    'Организация-владелец недоступна.'
                );
            }

            if (
                $owner->publicId
                !== $document->ownerOrganizationPublicId
            ) {
                $service->assignOrganizationOwner(
                    $document->publicId,
                    $owner->publicId,
                    $document->siteKey,
                );
            }

            $this->saveFile(
                $request,
                $document->publicId,
                $access->visibleOwnerPublicIds(
                    $userId,
                    'documents.edit',
                    $document->siteKey,
                ),
                $document->siteKey,
            );

            $pdo->commit();

            AuditLog::emit(
                eventType: 'document.updated',
                actorUserId: $userId,
                subjectType: 'document',
                subjectId: $document->publicId,
                request: $request,
            );

            Response::redirectLocal(
                '/admin/documents?status=updated',
            );
        } catch (InvalidArgumentException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'ChurchCMS document update: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/documents?status=invalid',
            );
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    public function publish(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'documents.publish',
        );

        $document = self::authorized(
            $request,
            $publicId,
            'documents.publish',
        );

        $service = DocumentService::fromDatabase();
        $service->publish(
            $document->publicId,
            $document->siteKey,
        );
        $service->setVisibility(
            $document->publicId,
            'public',
            $document->siteKey,
        );

        self::audit(
            $request,
            $document,
            'document.published',
        );

        Response::redirectLocal(
            '/admin/documents?status=published',
        );
    }

    public function withdraw(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'documents.publish',
        );

        $document = self::authorized(
            $request,
            $publicId,
            'documents.publish',
        );

        DocumentService::fromDatabase()->withdraw(
            $document->publicId,
            $document->siteKey,
        );

        self::audit(
            $request,
            $document,
            'document.withdrawn',
        );

        Response::redirectLocal(
            '/admin/documents?status=withdrawn',
        );
    }

    public function archive(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'documents.edit',
        );

        $document = self::authorized(
            $request,
            $publicId,
            'documents.edit',
        );

        DocumentMediaService::fromDatabase()
            ->detachFile(
                $document->publicId,
                $document->siteKey,
            );
        DocumentService::fromDatabase()->archive(
            $document->publicId,
            $document->siteKey,
        );

        self::audit(
            $request,
            $document,
            'document.archived',
        );

        Response::redirectLocal(
            '/admin/documents?status=archived',
        );
    }

    /**
     * @param list<string>|null $visibleOwners
     */
    private function saveFile(
        Request $request,
        string $documentPublicId,
        ?array $visibleOwners,
        string $siteKey = 'default',
    ): void {
        $mediaPublicId = trim((string) $request->post(
            'media_public_id',
            '',
        ));
        $media = DocumentMediaService::fromDatabase();

        $currentMediaPublicId = $media->fileMediaPublicId(
            $documentPublicId,
            $siteKey,
        );

        if ($mediaPublicId === '') {
            $media->detachFile(
                $documentPublicId,
                $siteKey,
            );
            return;
        }

        $allowed = [];
        foreach (
            $media->availableFiles(
                $visibleOwners,
                $siteKey,
            ) as $file
        ) {
            $id = (string) ($file['public_id'] ?? '');
            if ($id !== '') {
                $allowed[$id] = true;
            }
        }

        if (!isset($allowed[$mediaPublicId])) {
            throw new InvalidArgumentException(
                'Выбранный файл документа недоступен.'
            );
        }

        if ($currentMediaPublicId === $mediaPublicId) {
            return;
        }

        DocumentFileVersionService::fromDatabase()->record(
            $documentPublicId,
            $mediaPublicId,
            self::nullable(
                $request->post('file_version_note', null),
            ),
            $siteKey,
        );

        $media->attachFile(
            $documentPublicId,
            $mediaPublicId,
            $siteKey,
        );
    }

    private static function authorized(
        Request $request,
        string $publicId,
        string $permission,
    ): DocumentRecord {
        $document = self::document($publicId);

        if (!DocumentOrganizationAccessService::fromDatabase()
            ->canAccess(
                self::userId($request),
                $permission,
                $document,
            )
        ) {
            Response::text('403 Forbidden', 403);
        }

        return $document;
    }

    private static function document(
        string $publicId,
    ): DocumentRecord {
        $document = DocumentRepository::fromDatabase()
            ->findByPublicId(trim($publicId));

        if ($document === null) {
            Response::text('404 Not Found', 404);
        }

        return $document;
    }

    private static function audit(
        Request $request,
        DocumentRecord $document,
        string $eventType,
    ): void {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: self::userId($request),
            subjectType: 'document',
            subjectId: $document->publicId,
            request: $request,
        );
    }

    private static function userId(Request $request): int
    {
        $user = $request->attribute('admin.user');
        $id = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;

        if ($id <= 0) {
            Response::text('403 Forbidden', 403);
        }

        return $id;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @return array{kind:string,title:string,message:string}|null
     */
    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => [
                'kind' => 'success',
                'title' => 'Документ создан',
                'message' => 'Черновик документа сохранён.',
            ],
            'updated' => [
                'kind' => 'success',
                'title' => 'Документ обновлён',
                'message' => 'Карточка и файл документа сохранены.',
            ],
            'published' => [
                'kind' => 'success',
                'title' => 'Документ опубликован',
                'message' => 'Документ доступен в публичном каталоге.',
            ],
            'withdrawn' => [
                'kind' => 'success',
                'title' => 'Документ снят',
                'message' => 'Документ снова приватный черновик.',
            ],
            'archived' => [
                'kind' => 'success',
                'title' => 'Документ архивирован',
                'message' => 'Файловая ссылка снята.',
            ],
            'invalid' => [
                'kind' => 'error',
                'title' => 'Изменения не сохранены',
                'message' => 'Проверьте поля документа и доступный Media-файл.',
            ],
            default => null,
        };
    }
}
