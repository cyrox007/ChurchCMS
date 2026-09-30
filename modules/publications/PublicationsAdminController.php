<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\App\Services\AdminShell;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class PublicationsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.read');

        $userId = self::requiredUserId($request);
        $organizationAccess =
            PublicationOrganizationAccessService::fromDatabase();
        $ownerPublicIds = $organizationAccess->visibleOwnerPublicIds(
            $userId,
            'publications.read',
        );
        $publications = PublicationRepository::fromDatabase()->adminList(
            ownerPublicIds: $ownerPublicIds,
        );

        AdminShell::page($request, 'admin.publications.index', [
            'title' => 'Публикации',
            'publications' => $publications,
            'canCreate' => AdminAuthorization::can($request, 'publications.create'),
            'canEdit' => AdminAuthorization::can($request, 'publications.edit'),
            'canPublish' => AdminAuthorization::can($request, 'publications.publish'),
        ], 'publications');
    }

    public function createForm(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.create');

        $userId = self::requiredUserId($request);
        $defaultOwner = PublicationOrganizationAccessService::fromDatabase()
            ->defaultOwnerPublicId(
                $userId,
                'publications.create',
            );

        $this->renderEditor(
            request: $request,
            publication: null,
            form: $this->emptyForm($defaultOwner),
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.create');

        $form = $this->formFromRequest($request);
        $type = PublicationType::tryFrom($form['type']);

        if ($type === null) {
            $this->renderEditor($request, null, $form, 'Выберите тип публикации.');
        }

        if ($form['owner_organization_public_id'] === '') {
            $this->renderEditor(
                $request,
                null,
                $form,
                'Выберите организацию-владельца.',
            );
        }

        $owner = PublicationOrganizationAccessService::fromDatabase()
            ->assignableOwner(
                self::requiredUserId($request),
                'publications.create',
                $form['owner_organization_public_id'],
            );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $this->validateSeoMedia(
                $request,
                $form,
                'publications.create',
                'default',
            );

            $repository = PublicationRepository::fromDatabase();
            $publicId = PublicationService::fromDatabase()->createDraft(
                title: $form['title'],
                slug: $form['slug'],
                type: $type,
                excerpt: $form['excerpt'],
                bodyHtml: $form['body'],
                authorName: $form['author_name'],
                syndicationTargets: $this->targetsForRequest($request, []),
                commentsEnabled: $form['comments_enabled'],
                categoryNames: PublicationTaxonomyService::categoriesFromInput(
                    $form['categories'],
                ),
                tagNames: PublicationTaxonomyService::tagsFromInput(
                    $form['tags'],
                ),
                ownerOrganizationPublicId: $owner->publicId,
            );

            $publication = $repository->findByPublicId($publicId);
            if ($publication !== null) {
                $this->saveSeo(
                    $request,
                    $publication,
                    $form,
                    'publications.create',
                );
            }

            $this->audit($request, 'publication.created', $publicId);
            Response::redirectLocal('/admin/publications/' . rawurlencode($publicId) . '?saved=1');
        } catch (InvalidArgumentException) {
            $this->renderEditor(
                $request,
                null,
                $form,
                'Не удалось сохранить. Проверьте заголовок и дополнительные настройки.',
            );
        }
    }

    public function edit(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'publications.edit');

        $publication = PublicationRepository::fromDatabase()->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }

        $this->requirePublicationAccess(
            $request,
            $publication,
            'publications.edit',
        );

        $this->renderEditor(
            request: $request,
            publication: $publication,
            form: $this->formFromPublication($publication),
            success: $request->get('saved') === '1' ? 'Изменения сохранены.' : null,
        );
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'publications.edit');

        $repository = PublicationRepository::fromDatabase();
        $publication = $repository->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }

        $this->requirePublicationAccess(
            $request,
            $publication,
            'publications.edit',
        );

        $form = $this->formFromRequest($request);
        $type = PublicationType::tryFrom($form['type']);

        if ($type === null) {
            $this->renderEditor($request, $publication, $form, 'Выберите тип публикации.');
        }

        if ($form['owner_organization_public_id'] === '') {
            $this->renderEditor(
                $request,
                $publication,
                $form,
                'Выберите организацию-владельца.',
            );
        }

        $owner = PublicationOrganizationAccessService::fromDatabase()
            ->assignableOwner(
                self::requiredUserId($request),
                'publications.edit',
                $form['owner_organization_public_id'],
                $publication->siteKey,
            );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $this->validateSeoMedia(
                $request,
                $form,
                'publications.edit',
                $publication->siteKey,
            );

            $externalSettings =
                $this->validatedExternalSettings($request);

            PublicationService::fromDatabase()->update(
                publicId: $publicId,
                title: $form['title'],
                slug: $form['slug'],
                type: $type,
                excerpt: $form['excerpt'],
                bodyHtml: $form['body'],
                authorName: $form['author_name'],
                syndicationTargets: $this->targetsForRequest($request, $publication->syndicationTargets),
                commentsEnabled: $form['comments_enabled'],
                categoryNames: PublicationTaxonomyService::categoriesFromInput(
                    $form['categories'],
                ),
                tagNames: PublicationTaxonomyService::tagsFromInput(
                    $form['tags'],
                ),
                ownerOrganizationPublicId: $owner->publicId,
            );

            $updated = $repository->findByPublicId($publicId);
            if ($updated !== null) {
                $this->saveSeo(
                    $request,
                    $updated,
                    $form,
                    'publications.edit',
                );
                $this->saveExternalSelection(
                    $updated,
                    $externalSettings,
                );
            }

            $this->audit($request, 'publication.updated', $publicId);
            Response::redirectLocal('/admin/publications/' . rawurlencode($publicId) . '?saved=1');
        } catch (InvalidArgumentException) {
            $this->renderEditor(
                $request,
                $publication,
                $form,
                'Не удалось сохранить. Проверьте адрес материала, внешние каналы и дополнительные настройки.',
            );
        }
    }

    public function restoreRevision(
        Request $request,
        string $publicId,
        string $revisionPublicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'publications.edit',
        );

        $publication = PublicationRepository::fromDatabase()
            ->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }

        $this->requirePublicationAccess(
            $request,
            $publication,
            'publications.edit',
        );

        try {
            $revisions = PublicationRevisionService::fromDatabase();
            $revision = $revisions->revision(
                $publication->publicId,
                $revisionPublicId,
                $publication->siteKey,
            );
            $ownerPublicId = is_string(
                $revision->snapshot['owner_organization_public_id']
                    ?? null
            )
                ? trim((string) $revision->snapshot[
                    'owner_organization_public_id'
                ])
                : '';

            if (
                $ownerPublicId === ''
                || PublicationOrganizationAccessService::fromDatabase()
                    ->assignableOwner(
                        self::requiredUserId($request),
                        'publications.edit',
                        $ownerPublicId,
                        $publication->siteKey,
                    ) === null
            ) {
                Response::text('403 Forbidden', 403);
            }

            $revisions->restore(
                $publication->publicId,
                $revision->publicId,
                $publication->siteKey,
            );

            $this->audit(
                $request,
                'publication.revision_restored',
                $publication->publicId,
            );

            Response::redirectLocal(
                '/admin/publications/'
                . rawurlencode($publication->publicId)
                . '?saved=1'
            );
        } catch (InvalidArgumentException $error) {
            $this->renderEditor(
                $request,
                $publication,
                $this->formFromPublication($publication),
                $error->getMessage(),
            );
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'publications.publish');

        $publication = PublicationRepository::fromDatabase()
            ->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }
        $this->requirePublicationAccess(
            $request,
            $publication,
            'publications.publish',
        );

        PublicationService::fromDatabase()->publish($publicId);
        $this->queueExternalPublication($publication);
        $this->audit($request, 'publication.published', $publicId);

        Response::redirectLocal('/admin/publications/' . rawurlencode($publicId) . '?saved=1');
    }

    public function schedule(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'publications.publish',
        );

        $repository = PublicationRepository::fromDatabase();
        $publication = $repository->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }
        $this->requirePublicationAccess(
            $request,
            $publication,
            'publications.publish',
        );

        $raw = trim((string) $request->post(
            'scheduled_at',
            '',
        ));

        try {
            $timezone = $this->applicationTimezone();
            $when = DateTimeImmutable::createFromFormat(
                '!Y-m-d\\TH:i',
                $raw,
                $timezone,
            );

            if (
                !$when instanceof DateTimeImmutable
                || $when->format('Y-m-d\\TH:i') !== $raw
            ) {
                throw new InvalidArgumentException(
                    'Укажите корректные дату и время публикации.'
                );
            }

            PublicationService::fromDatabase()->schedule(
                $publicId,
                $when,
            );
            $this->audit(
                $request,
                'publication.scheduled',
                $publicId,
            );

            Response::redirectLocal(
                '/admin/publications/'
                . rawurlencode($publicId)
                . '?saved=1'
            );
        } catch (InvalidArgumentException) {
            $this->renderEditor(
                $request,
                $publication,
                $this->formFromPublication($publication),
                'Не удалось запланировать публикацию. Проверьте дату и время.',
            );
        }
    }

    public function unschedule(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'publications.publish',
        );

        $publication = PublicationRepository::fromDatabase()
            ->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }
        $this->requirePublicationAccess(
            $request,
            $publication,
            'publications.publish',
        );

        try {
            PublicationService::fromDatabase()->unschedule(
                $publicId,
            );
            $this->audit(
                $request,
                'publication.unscheduled',
                $publicId,
            );

            Response::redirectLocal(
                '/admin/publications/'
                . rawurlencode($publicId)
                . '?saved=1'
            );
        } catch (InvalidArgumentException) {
            Response::redirectLocal(
                '/admin/publications/'
                . rawurlencode($publicId)
            );
        }
    }

    public function withdraw(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'publications.publish');

        $publication = PublicationRepository::fromDatabase()
            ->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }
        $this->requirePublicationAccess(
            $request,
            $publication,
            'publications.publish',
        );

        PublicationService::fromDatabase()->withdraw($publicId);
        $this->audit($request, 'publication.withdrawn', $publicId);

        Response::redirectLocal('/admin/publications/' . rawurlencode($publicId) . '?saved=1');
    }

    private function renderEditor(
        Request $request,
        ?Publication $publication,
        array $form,
        ?string $error = null,
        ?string $success = null,
    ): never {
        $permission = $publication === null
            ? 'publications.create'
            : 'publications.edit';
        $organizationAccess =
            PublicationOrganizationAccessService::fromDatabase();
        $organizationUnits = $organizationAccess->availableOwners(
            self::requiredUserId($request),
            $permission,
            $publication?->siteKey ?? 'default',
        );

        if ($organizationUnits === []) {
            Response::text('403 Forbidden', 403);
        }

        $canPublish = AdminAuthorization::can(
            $request,
            'publications.publish',
        );
        if ($canPublish && $publication !== null) {
            $canPublish = $organizationAccess->canAccess(
                self::requiredUserId($request),
                'publications.publish',
                $publication,
            );
        }

        $seoMediaOptions = [
            'images' => [],
            'videos' => [],
        ];
        $seoCapability = ModuleRuntimeLoader::capability(
            'seo',
            'seo.publications',
        );

        if (
            $seoCapability !== null
            && method_exists(
                $seoCapability,
                'structuredMediaOptions',
            )
        ) {
            $seoMediaOptions =
                $seoCapability->structuredMediaOptions(
                    $organizationAccess->visibleOwnerPublicIds(
                        self::requiredUserId($request),
                        $permission,
                        $publication?->siteKey ?? 'default',
                    ),
                    $publication?->siteKey ?? 'default',
                );
        }

        $canExternalPublish = AdminAuthorization::can(
            $request,
            'social.publish',
        );
        $externalChannels = $this->externalEditorConnections(
            $publication,
            $form,
            $canExternalPublish,
        );

        AdminShell::page($request, 'admin.publications.editor', [
            'title' => $publication === null ? 'Новая публикация' : 'Редактирование публикации',
            'publication' => $publication,
            'form' => $form,
            'error' => $error,
            'success' => $success,
            'organizationUnits' => $organizationUnits,
            'canPublish' => $canPublish,
            'canSyndicate' => AdminAuthorization::can($request, 'publications.syndicate'),
            'canExternalPublish' => $canExternalPublish,
            'externalChannels' => $externalChannels,
            'seoMediaOptions' => $seoMediaOptions,
            'revisions' => $publication !== null
                && AdminAuthorization::can(
                    $request,
                    'publications.edit',
                )
                    ? PublicationRevisionService::fromDatabase()
                        ->history(
                            $publication->publicId,
                            $publication->siteKey,
                        )
                    : [],
            'scheduleTimezone' => $this->applicationTimezone()
                ->getName(),
            'scheduledAtLocal' => $this->scheduledAtLocal(
                $publication,
            ),
        ], 'publications');
    }

    private function formFromRequest(Request $request): array
    {
        return [
            'type' => (string) $request->post('type', PublicationType::News->value),
            'title' => trim((string) $request->post('title', '')),
            'excerpt' => trim((string) $request->post('excerpt', '')),
            'body' => trim((string) $request->post('body', '')),
            'author_name' => trim((string) $request->post('author_name', '')),
            'owner_organization_public_id' => trim((string) $request->post(
                'owner_organization_public_id',
                '',
            )),
            'slug' => trim((string) $request->post('slug', '')),
            'comments_enabled' => $request->post('comments_enabled') === '1',
            'categories' => trim((string) $request->post('categories', '')),
            'tags' => trim((string) $request->post('tags', '')),
            'external_channel_ids' => self::stringList(
                $request->post('external_channel_ids', []),
            ),
            'external_channel_text' => self::stringMap(
                $request->post('external_channel_text', []),
            ),
            'seo_title' => trim((string) $request->post('seo_title', '')),
            'seo_description' => trim((string) $request->post('seo_description', '')),
            'seo_keywords' => trim((string) $request->post('seo_keywords', '')),
            'canonical_url' => trim((string) $request->post('canonical_url', '')),
            'social_title' => trim((string) $request->post('social_title', '')),
            'social_description' => trim((string) $request->post('social_description', '')),
            'social_image_url' => trim((string) $request->post('social_image_url', '')),
            'seo_image_media_id' => trim((string) $request->post('seo_image_media_id', '')),
            'seo_video_media_id' => trim((string) $request->post('seo_video_media_id', '')),
            'seo_video_thumbnail_media_id' => trim((string) $request->post('seo_video_thumbnail_media_id', '')),
            'robots_index' => $request->post('robots_index') === '1',
            'robots_follow' => $request->post('robots_follow') === '1',
        ];
    }

    private function formFromPublication(Publication $publication): array
    {
        $taxonomy = PublicationTaxonomyService::fromDatabase()
            ->forPublication($publication->id);

        $form = [
            'type' => $publication->type->value,
            'title' => $publication->title,
            'excerpt' => $publication->excerpt,
            'body' => self::editorText($publication->bodyHtml),
            'author_name' => $publication->authorName ?? '',
            'owner_organization_public_id' =>
                $publication->ownerOrganizationPublicId ?? '',
            'slug' => $publication->slug,
            'comments_enabled' => $publication->commentsEnabled,
            'categories' => PublicationTaxonomyService::names(
                $taxonomy['categories'],
            ),
            'tags' => PublicationTaxonomyService::names(
                $taxonomy['tags'],
            ),
        ];

        $capability = ModuleRuntimeLoader::capability('seo', 'seo.publications');
        if ($capability !== null && method_exists($capability, 'formForPublication')) {
            $seo = $capability->formForPublication($publication);
            if (is_array($seo)) {
                $form = array_replace($form, $seo);
            }
        }

        return $form + $this->seoDefaults();
    }

    private function emptyForm(
        string $ownerOrganizationPublicId = '',
    ): array
    {
        return [
            'type' => PublicationType::News->value,
            'title' => '',
            'excerpt' => '',
            'body' => '',
            'author_name' => '',
            'owner_organization_public_id' =>
                $ownerOrganizationPublicId,
            'slug' => '',
            'comments_enabled' => false,
            'categories' => '',
            'tags' => '',
        ] + $this->seoDefaults();
    }

    private function seoDefaults(): array
    {
        return [
            'seo_title' => '',
            'seo_description' => '',
            'seo_keywords' => '',
            'canonical_url' => '',
            'social_title' => '',
            'social_description' => '',
            'social_image_url' => '',
            'seo_image_media_id' => '',
            'seo_video_media_id' => '',
            'seo_video_thumbnail_media_id' => '',
            'robots_index' => true,
            'robots_follow' => true,
        ];
    }

    /**
     * @return list<string>|null
     */
    /**
     * @return array{
     *     ids:list<string>,
     *     texts:array<string,?string>
     * }|null
     */
    private function validatedExternalSettings(
        Request $request,
    ): ?array {
        if (!AdminAuthorization::can($request, 'social.publish')) {
            return null;
        }

        $capability = ModuleRuntimeLoader::capability(
            'social',
            'social.publication',
        );
        if (
            $capability === null
            || !method_exists(
                $capability,
                'validatePublicationSelection',
            )
            || !method_exists(
                $capability,
                'validatePublicationCustomTexts',
            )
        ) {
            return null;
        }

        $ids = $capability->validatePublicationSelection(
            self::stringList(
                $request->post(
                    'external_channel_ids',
                    [],
                ),
            ),
        );
        $texts = $capability->validatePublicationCustomTexts(
            $ids,
            self::stringMap(
                $request->post(
                    'external_channel_text',
                    [],
                ),
            ),
        );

        return [
            'ids' => $ids,
            'texts' => $texts,
        ];
    }

    /**
     * @param array{
     *     ids:list<string>,
     *     texts:array<string,?string>
     * }|null $settings
     */
    private function saveExternalSelection(
        Publication $publication,
        ?array $settings,
    ): void {
        if ($settings === null) {
            return;
        }

        $capability = ModuleRuntimeLoader::capability(
            'social',
            'social.publication',
        );
        if (
            $capability === null
            || !method_exists(
                $capability,
                'savePublicationSelection',
            )
        ) {
            return;
        }

        $capability->savePublicationSelection(
            $publication->id,
            $settings['ids'],
            $settings['texts'],
        );

        if (
            $publication->isPublicNow(
                new DateTimeImmutable('now'),
            )
            && method_exists(
                $capability,
                'queuePublication',
            )
        ) {
            $capability->queuePublication(
                $publication->id,
            );
        }
    }

    private function queueExternalPublication(
        Publication $publication,
    ): void {
        $capability = ModuleRuntimeLoader::capability(
            'social',
            'social.publication',
        );
        if (
            $capability === null
            || !method_exists($capability, 'queuePublication')
        ) {
            return;
        }

        $capability->queuePublication($publication->id);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function externalEditorConnections(
        ?Publication $publication,
        array $form,
        bool $allowed,
    ): array {
        if (!$allowed || $publication === null) {
            return [];
        }

        $capability = ModuleRuntimeLoader::capability(
            'social',
            'social.publication',
        );
        if (
            $capability === null
            || !method_exists(
                $capability,
                'publicationEditorConnections',
            )
        ) {
            return [];
        }

        $connections = $capability->publicationEditorConnections(
            $publication->id,
        );
        $requested = $form['external_channel_ids'] ?? null;
        if (!is_array($requested)) {
            return $connections;
        }

        $selected = array_fill_keys(
            self::stringList($requested),
            true,
        );
        $requestedTexts = self::stringMap(
            $form['external_channel_text'] ?? [],
        );

        foreach ($connections as &$connection) {
            $publicId = (string) (
                $connection['public_id'] ?? ''
            );
            $connection['selected'] = isset(
                $selected[$publicId]
            );

            if (array_key_exists($publicId, $requestedTexts)) {
                $connection['custom_text'] =
                    $requestedTexts[$publicId];
            }
        }
        unset($connection);

        return $connections;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item !== '') {
                $result[$item] = true;
            }
        }

        return array_keys($result);
    }

    /**
     * @return array<string,string>
     */
    private static function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_string($item)) {
                continue;
            }

            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }

    private function applicationTimezone(): DateTimeZone
    {
        try {
            return new DateTimeZone(
                (string) Config::get(
                    'app.timezone',
                    'UTC',
                ),
            );
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    private function scheduledAtLocal(
        ?Publication $publication,
    ): string {
        if (
            $publication === null
            || $publication->status !== PublicationStatus::Scheduled
            || $publication->publishedAt === null
        ) {
            return '';
        }

        $timezone = $this->applicationTimezone();

        return $publication->publishedAt
            ->setTimezone($timezone)
            ->format('Y-m-d\\TH:i');
    }

    private function validateSeoMedia(
        Request $request,
        array $form,
        string $permission,
        string $siteKey,
    ): void {
        $capability = ModuleRuntimeLoader::capability(
            'seo',
            'seo.publications',
        );

        if (
            $capability === null
            || !method_exists(
                $capability,
                'validateStructuredMediaSelection',
            )
        ) {
            return;
        }

        $ownerPublicIds =
            PublicationOrganizationAccessService::fromDatabase()
                ->visibleOwnerPublicIds(
                    self::requiredUserId($request),
                    $permission,
                    $siteKey,
                );

        $capability->validateStructuredMediaSelection(
            $form,
            $ownerPublicIds,
            $siteKey,
        );
    }

    private function saveSeo(
        Request $request,
        Publication $publication,
        array $form,
        string $permission,
    ): void {
        $capability = ModuleRuntimeLoader::capability(
            'seo',
            'seo.publications',
        );
        if (
            $capability === null
            || !method_exists(
                $capability,
                'savePublication',
            )
        ) {
            return;
        }

        $ownerPublicIds =
            PublicationOrganizationAccessService::fromDatabase()
                ->visibleOwnerPublicIds(
                    self::requiredUserId($request),
                    $permission,
                    $publication->siteKey,
                );

        $capability->savePublication(
            $publication,
            [
                'seo_title' => $form['seo_title'] ?? '',
                'seo_description' =>
                    $form['seo_description'] ?? '',
                'seo_keywords' =>
                    $form['seo_keywords'] ?? '',
                'canonical_url' =>
                    $form['canonical_url'] ?? '',
                'social_title' =>
                    $form['social_title'] ?? '',
                'social_description' =>
                    $form['social_description'] ?? '',
                'social_image_url' =>
                    $form['social_image_url'] ?? '',
                'seo_image_media_id' =>
                    $form['seo_image_media_id'] ?? '',
                'seo_video_media_id' =>
                    $form['seo_video_media_id'] ?? '',
                'seo_video_thumbnail_media_id' =>
                    $form['seo_video_thumbnail_media_id']
                        ?? '',
                'robots_index' =>
                    ($form['robots_index'] ?? true) === true,
                'robots_follow' =>
                    ($form['robots_follow'] ?? true) === true,
            ],
            $ownerPublicIds,
        );
    }

    /**
     * @param list<string> $fallback
     * @return list<string>
     */
    private function targetsForRequest(Request $request, array $fallback): array
    {
        if (!AdminAuthorization::can($request, 'publications.syndicate')) {
            return $fallback;
        }

        $raw = $request->post('syndication_targets', []);
        if (!is_array($raw)) {
            return [];
        }

        $known = ['rss', 'diocese', 'rambler'];
        return array_values(array_intersect($known, array_filter($raw, 'is_string')));
    }

    private function audit(Request $request, string $event, string $publicId): void
    {
        $user = $request->attribute('admin.user');
        $actor = is_array($user) ? (int) ($user['id'] ?? 0) : null;

        AuditLog::emit(
            eventType: $event,
            actorUserId: $actor,
            subjectType: 'publication',
            subjectId: $publicId,
            request: $request,
        );
    }

    private function requirePublicationAccess(
        Request $request,
        Publication $publication,
        string $permission,
    ): void {
        if (
            !PublicationOrganizationAccessService::fromDatabase()
                ->canAccess(
                    self::requiredUserId($request),
                    $permission,
                    $publication,
                )
        ) {
            Response::text('403 Forbidden', 403);
        }
    }

    private static function requiredUserId(Request $request): int
    {
        $user = $request->attribute('admin.user');
        $userId = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;

        if ($userId <= 0) {
            Response::text('403 Forbidden', 403);
        }

        return $userId;
    }

    private static function editorText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p\s*>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }
}
