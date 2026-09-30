<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class NavigationMenuAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'pages.edit',
        );

        $userId = self::requiredUserId($request);
        $service = NavigationMenuService::fromDatabase();
        $menu = $service->ensurePrimary();

        $pages = PageRepository::fromDatabase()->adminTree(
            ownerPublicIds:
                PageOrganizationAccessService::fromDatabase()
                    ->visibleOwnerPublicIds(
                        $userId,
                        'pages.edit',
                    ),
        );

        AdminShell::page(
            $request,
            'admin.navigation',
            [
                'title' => 'Меню',
                'menu' => $menu,
                'menuItems' => $service->items(),
                'pages' => $pages,
                'systemRoutes' => $service->systemRoutes(),
                'navigationStatus' => self::status($request),
            ],
            'navigation',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'pages.edit',
        );

        $userId = self::requiredUserId($request);
        $form = self::form($request);
        self::requirePageAccess(
            $userId,
            $form['item_type'],
            $form['page_public_id'],
        );

        try {
            $item = NavigationMenuService::fromDatabase()
                ->createItem(
                    itemType: $form['item_type'],
                    label: $form['label'],
                    pagePublicId: self::nullable(
                        $form['page_public_id'],
                    ),
                    routeName: self::nullable(
                        $form['route_name'],
                    ),
                    externalUrl: self::nullable(
                        $form['external_url'],
                    ),
                    sortOrder: $form['sort_order'],
                    enabled: $form['enabled'],
                );

            self::audit(
                $request,
                'navigation.item.created',
                $item->publicId,
            );

            Response::redirectLocal(
                '/admin/navigation?status=created',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS создание пункта меню: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/navigation?status=invalid',
            );
        }
    }

    public function update(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'pages.edit',
        );

        $userId = self::requiredUserId($request);
        $form = self::form($request);
        self::requirePageAccess(
            $userId,
            $form['item_type'],
            $form['page_public_id'],
        );

        try {
            NavigationMenuService::fromDatabase()
                ->updateItem(
                    publicId: $publicId,
                    itemType: $form['item_type'],
                    label: $form['label'],
                    pagePublicId: self::nullable(
                        $form['page_public_id'],
                    ),
                    routeName: self::nullable(
                        $form['route_name'],
                    ),
                    externalUrl: self::nullable(
                        $form['external_url'],
                    ),
                    sortOrder: $form['sort_order'],
                    enabled: $form['enabled'],
                );

            self::audit(
                $request,
                'navigation.item.updated',
                $publicId,
            );

            Response::redirectLocal(
                '/admin/navigation?status=updated',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS обновление пункта меню: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/navigation?status=invalid',
            );
        }
    }

    public function delete(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'pages.edit',
        );

        try {
            NavigationMenuService::fromDatabase()
                ->deleteItem($publicId);

            self::audit(
                $request,
                'navigation.item.deleted',
                $publicId,
            );

            Response::redirectLocal(
                '/admin/navigation?status=deleted',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS удаление пункта меню: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/navigation?status=invalid',
            );
        }
    }

    /**
     * @return array{
     *     item_type:string,
     *     label:string,
     *     page_public_id:string,
     *     route_name:string,
     *     external_url:string,
     *     sort_order:int,
     *     enabled:bool
     * }
     */
    private static function form(Request $request): array
    {
        return [
            'item_type' => trim(
                (string) $request->post(
                    'item_type',
                    '',
                ),
            ),
            'label' => trim(
                (string) $request->post(
                    'label',
                    '',
                ),
            ),
            'page_public_id' => trim(
                (string) $request->post(
                    'page_public_id',
                    '',
                ),
            ),
            'route_name' => trim(
                (string) $request->post(
                    'route_name',
                    '',
                ),
            ),
            'external_url' => trim(
                (string) $request->post(
                    'external_url',
                    '',
                ),
            ),
            'sort_order' => (int) $request->post(
                'sort_order',
                0,
            ),
            'enabled' => $request->post(
                'enabled',
                null,
            ) !== null,
        ];
    }

    private static function requirePageAccess(
        int $userId,
        string $itemType,
        string $pagePublicId,
    ): void {
        if ($itemType !== 'page') {
            return;
        }

        $page = PageRepository::fromDatabase()
            ->findByPublicId($pagePublicId);

        if (
            $page === null
            || !PageOrganizationAccessService::fromDatabase()
                ->canAccess(
                    $userId,
                    'pages.edit',
                    $page,
                )
        ) {
            Response::text('403 Forbidden', 403);
        }
    }

    private static function audit(
        Request $request,
        string $event,
        string $publicId,
    ): void {
        AuditLog::emit(
            eventType: $event,
            actorUserId: self::requiredUserId($request),
            subjectType: 'navigation_menu_item',
            subjectId: $publicId,
            request: $request,
        );
    }

    private static function requiredUserId(
        Request $request,
    ): int {
        $user = $request->attribute('admin.user');
        $userId = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;

        if ($userId <= 0) {
            Response::text('403 Forbidden', 403);
        }

        return $userId;
    }

    private static function nullable(
        string $value,
    ): ?string {
        return $value !== '' ? $value : null;
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
            'created' => [
                'kind' => 'success',
                'title' => 'Пункт добавлен',
                'message' => 'Основное меню обновлено.',
            ],
            'updated' => [
                'kind' => 'success',
                'title' => 'Пункт сохранён',
                'message' => 'Изменения меню применены.',
            ],
            'deleted' => [
                'kind' => 'success',
                'title' => 'Пункт удалён',
                'message' => 'Основное меню обновлено.',
            ],
            'invalid' => [
                'kind' => 'error',
                'title' => 'Изменения не сохранены',
                'message' => 'Проверьте название, цель ссылки и порядок.',
            ],
            default => null,
        };
    }
}
