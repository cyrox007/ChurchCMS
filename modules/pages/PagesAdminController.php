<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class PagesAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'pages.read');

        $userId = self::requiredUserId($request);
        $access = PageOrganizationAccessService::fromDatabase();
        $pages = PageRepository::fromDatabase()->adminTree(
            ownerPublicIds: $access->visibleOwnerPublicIds(
                $userId,
                'pages.read',
            ),
        );
        $editablePageIds = [];

        if (AdminAuthorization::can($request, 'pages.edit')) {
            foreach ($pages as $page) {
                if ($access->canAccessSubtree(
                    $userId,
                    'pages.edit',
                    $page,
                )) {
                    $editablePageIds[$page->publicId] = true;
                }
            }
        }

        AdminShell::page($request, 'admin.pages.index', [
            'title' => 'Страницы',
            'pages' => $pages,
            'editablePageIds' => $editablePageIds,
            'canCreate' => AdminAuthorization::can(
                $request,
                'pages.create',
            ),
        ], 'pages');
    }

    public function createForm(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'pages.create');

        $defaultOwner = PageOrganizationAccessService::fromDatabase()
            ->defaultOwnerPublicId(
                self::requiredUserId($request),
                'pages.create',
            );

        $this->renderEditor(
            $request,
            null,
            $this->emptyForm($defaultOwner),
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'pages.create');

        $form = $this->formFromRequest($request);
        $userId = self::requiredUserId($request);
        $access = PageOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'pages.create',
            $form['owner_organization_public_id'],
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        if (!$this->canUseParent(
            $access,
            $userId,
            'pages.create',
            $form['parent_public_id'],
        )) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = PageService::fromDatabase()->createDraft(
                title: $form['title'],
                slug: $form['slug'],
                bodyInput: $form['body'],
                navigationTitle: $form['navigation_title'],
                parentPublicId: self::nullable(
                    $form['parent_public_id'],
                ),
                sortOrder: $form['sort_order'],
                ownerOrganizationPublicId: $owner->publicId,
            );

            $this->audit($request, 'page.created', $publicId);
            Response::redirectLocal(
                '/admin/pages/' . rawurlencode($publicId) . '?saved=1'
            );
        } catch (InvalidArgumentException $error) {
            $this->renderEditor(
                $request,
                null,
                $form,
                $error->getMessage(),
            );
        }
    }

    public function edit(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'pages.edit');

        $page = $this->requiredPage($publicId);
        $this->requirePageSubtreeAccess(
            $request,
            $page,
            'pages.edit',
        );

        $this->renderEditor(
            $request,
            $page,
            $this->formFromPage($page),
            success: $request->get('saved') === '1'
                ? 'Изменения сохранены.'
                : null,
        );
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'pages.edit');

        $page = $this->requiredPage($publicId);
        $this->requirePageSubtreeAccess(
            $request,
            $page,
            'pages.edit',
        );

        $form = $this->formFromRequest($request);
        $userId = self::requiredUserId($request);
        $access = PageOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'pages.edit',
            $form['owner_organization_public_id'],
            $page->siteKey,
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        if (!$this->canUseParent(
            $access,
            $userId,
            'pages.edit',
            $form['parent_public_id'],
            $page->siteKey,
        )) {
            Response::text('403 Forbidden', 403);
        }

        try {
            PageService::fromDatabase()->update(
                publicId: $publicId,
                title: $form['title'],
                slug: $form['slug'],
                bodyInput: $form['body'],
                navigationTitle: $form['navigation_title'],
                parentPublicId: self::nullable(
                    $form['parent_public_id'],
                ),
                sortOrder: $form['sort_order'],
                siteKey: $page->siteKey,
                ownerOrganizationPublicId: $owner->publicId,
            );

            $this->audit($request, 'page.updated', $publicId);
            Response::redirectLocal(
                '/admin/pages/' . rawurlencode($publicId) . '?saved=1'
            );
        } catch (InvalidArgumentException $error) {
            $this->renderEditor(
                $request,
                $page,
                $form,
                $error->getMessage(),
            );
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'pages.publish');

        $page = $this->requiredPage($publicId);
        $this->requirePageAccess(
            $request,
            $page,
            'pages.publish',
        );

        try {
            PageService::fromDatabase()->publish(
                $publicId,
                $page->siteKey,
            );
            $this->audit($request, 'page.published', $publicId);
            Response::redirectLocal(
                '/admin/pages/' . rawurlencode($publicId) . '?saved=1'
            );
        } catch (InvalidArgumentException $error) {
            $this->renderEditor(
                $request,
                $page,
                $this->formFromPage($page),
                $error->getMessage(),
            );
        }
    }

    public function unpublish(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'pages.publish');

        $page = $this->requiredPage($publicId);
        $this->requirePageSubtreeAccess(
            $request,
            $page,
            'pages.publish',
        );
        PageService::fromDatabase()->unpublish(
            $publicId,
            $page->siteKey,
        );
        $this->audit($request, 'page.unpublished', $publicId);

        Response::redirectLocal(
            '/admin/pages/' . rawurlencode($publicId) . '?saved=1'
        );
    }

    private function renderEditor(
        Request $request,
        ?Page $page,
        array $form,
        ?string $error = null,
        ?string $success = null,
    ): never {
        $permission = $page === null
            ? 'pages.create'
            : 'pages.edit';
        $userId = self::requiredUserId($request);
        $access = PageOrganizationAccessService::fromDatabase();
        $owners = $access->availableOwners(
            $userId,
            $permission,
            $page?->siteKey ?? 'default',
        );

        if ($owners === []) {
            Response::text('403 Forbidden', 403);
        }

        $parentPages = PageRepository::fromDatabase()->adminTree(
            $page?->siteKey ?? 'default',
            $access->visibleOwnerPublicIds(
                $userId,
                $permission,
                $page?->siteKey ?? 'default',
            ),
        );

        if ($page !== null) {
            $parentPages = array_values(array_filter(
                $parentPages,
                static fn(Page $candidate): bool =>
                    $candidate->id !== $page->id
                    && !str_starts_with(
                        $candidate->path,
                        $page->path . '/',
                    ),
            ));
        }

        $canPublish = AdminAuthorization::can(
            $request,
            'pages.publish',
        );
        if ($canPublish && $page !== null) {
            $canPublish = $page->status === PageStatus::Published
                ? $access->canAccessSubtree(
                    $userId,
                    'pages.publish',
                    $page,
                )
                : $access->canAccess(
                    $userId,
                    'pages.publish',
                    $page,
                );
        }

        AdminShell::page($request, 'admin.pages.editor', [
            'title' => $page === null
                ? 'Новая страница'
                : 'Редактирование страницы',
            'page' => $page,
            'form' => $form,
            'error' => $error,
            'success' => $success,
            'organizationUnits' => $owners,
            'parentPages' => $parentPages,
            'canPublish' => $canPublish,
        ], 'pages');
    }

    private function formFromRequest(Request $request): array
    {
        return [
            'title' => trim((string) $request->post('title', '')),
            'navigation_title' => trim((string) $request->post(
                'navigation_title',
                '',
            )),
            'body' => trim((string) $request->post('body', '')),
            'slug' => trim((string) $request->post('slug', '')),
            'parent_public_id' => trim((string) $request->post(
                'parent_public_id',
                '',
            )),
            'owner_organization_public_id' => trim((string) $request->post(
                'owner_organization_public_id',
                '',
            )),
            'sort_order' => (int) $request->post('sort_order', 0),
        ];
    }

    private function formFromPage(Page $page): array
    {
        $parentPublicId = '';
        if ($page->parentId !== null) {
            $parentPublicId = PageRepository::fromDatabase()
                ->publicIdsByIds(
                    [$page->parentId],
                    $page->siteKey,
                )[$page->parentId] ?? '';
        }

        return [
            'title' => $page->title,
            'navigation_title' => $page->navigationTitle ?? '',
            'body' => self::editorText($page->bodyHtml),
            'slug' => $page->slug,
            'parent_public_id' => $parentPublicId,
            'owner_organization_public_id' =>
                $page->ownerOrganizationPublicId ?? '',
            'sort_order' => $page->sortOrder,
        ];
    }

    private function emptyForm(string $ownerPublicId): array
    {
        return [
            'title' => '',
            'navigation_title' => '',
            'body' => '',
            'slug' => '',
            'parent_public_id' => '',
            'owner_organization_public_id' => $ownerPublicId,
            'sort_order' => 0,
        ];
    }

    private function canUseParent(
        PageOrganizationAccessService $access,
        int $userId,
        string $permission,
        string $parentPublicId,
        string $siteKey = 'default',
    ): bool {
        if ($parentPublicId === '') {
            return true;
        }

        $parent = PageRepository::fromDatabase()->findByPublicId(
            $parentPublicId,
            $siteKey,
        );

        return $parent !== null
            && $access->canAccess(
                $userId,
                $permission,
                $parent,
            );
    }

    private function requiredPage(string $publicId): Page
    {
        $page = PageRepository::fromDatabase()->findByPublicId($publicId);
        if ($page === null) {
            Response::text('404 Not Found', 404);
        }

        return $page;
    }

    private function requirePageAccess(
        Request $request,
        Page $page,
        string $permission,
    ): void {
        if (!PageOrganizationAccessService::fromDatabase()->canAccess(
            self::requiredUserId($request),
            $permission,
            $page,
        )) {
            Response::text('403 Forbidden', 403);
        }
    }

    private function requirePageSubtreeAccess(
        Request $request,
        Page $page,
        string $permission,
    ): void {
        if (!PageOrganizationAccessService::fromDatabase()->canAccessSubtree(
            self::requiredUserId($request),
            $permission,
            $page,
        )) {
            Response::text('403 Forbidden', 403);
        }
    }

    private function audit(
        Request $request,
        string $event,
        string $publicId,
    ): void {
        $user = $request->attribute('admin.user');
        $actor = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : null;

        AuditLog::emit(
            eventType: $event,
            actorUserId: $actor,
            subjectType: 'page',
            subjectId: $publicId,
            request: $request,
        );
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

    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private static function editorText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p\s*>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        return trim($text);
    }
}
