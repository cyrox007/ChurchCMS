<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use PDO;

final class NavigationMenuRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function findByKey(
        string $menuKey,
        string $siteKey = 'default',
    ): ?NavigationMenu {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM navigation_menus
             WHERE site_key = :site_key
               AND menu_key = :menu_key
             LIMIT 1'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'menu_key' => $menuKey,
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrateMenu($row)
            : null;
    }

    public function create(
        string $menuKey,
        string $name,
        string $siteKey = 'default',
    ): NavigationMenu {
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO navigation_menus (
                public_id,
                site_key,
                menu_key,
                name,
                enabled,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :menu_key,
                :name,
                :enabled,
                :created_at,
                :updated_at
             )'
        );

        $statement->execute([
            'public_id' => Uuid::v4(),
            'site_key' => $siteKey,
            'menu_key' => $menuKey,
            'name' => $name,
            'enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $menu = $this->findByKey(
            $menuKey,
            $siteKey,
        );

        if ($menu === null) {
            throw new \RuntimeException(
                'Созданное меню не удалось перечитать.'
            );
        }

        return $menu;
    }

    /**
     * @return list<NavigationMenuItem>
     */
    public function items(int $menuId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM navigation_menu_items
             WHERE menu_id = :menu_id
             ORDER BY sort_order, id'
        );
        $statement->execute([
            'menu_id' => $menuId,
        ]);

        return array_map(
            self::hydrateItem(...),
            $statement->fetchAll(),
        );
    }

    public function findItem(
        string $publicId,
        int $menuId,
    ): ?NavigationMenuItem {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM navigation_menu_items
             WHERE public_id = :public_id
               AND menu_id = :menu_id
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($publicId),
            'menu_id' => $menuId,
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrateItem($row)
            : null;
    }

    public function createItem(
        int $menuId,
        string $itemType,
        string $label,
        ?string $pagePublicId,
        ?string $routeName,
        ?string $externalUrl,
        int $sortOrder,
        bool $enabled,
    ): NavigationMenuItem {
        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO navigation_menu_items (
                public_id,
                menu_id,
                item_type,
                label,
                page_public_id,
                route_name,
                external_url,
                sort_order,
                enabled,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :menu_id,
                :item_type,
                :label,
                :page_public_id,
                :route_name,
                :external_url,
                :sort_order,
                :enabled,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'menu_id' => $menuId,
            'item_type' => $itemType,
            'label' => $label,
            'page_public_id' => $pagePublicId,
            'route_name' => $routeName,
            'external_url' => $externalUrl,
            'sort_order' => $sortOrder,
            'enabled' => $enabled ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $item = $this->findItem(
            $publicId,
            $menuId,
        );

        if ($item === null) {
            throw new \RuntimeException(
                'Созданный пункт меню не удалось перечитать.'
            );
        }

        return $item;
    }

    public function updateItem(
        NavigationMenuItem $item,
        string $itemType,
        string $label,
        ?string $pagePublicId,
        ?string $routeName,
        ?string $externalUrl,
        int $sortOrder,
        bool $enabled,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE navigation_menu_items
             SET item_type = :item_type,
                 label = :label,
                 page_public_id = :page_public_id,
                 route_name = :route_name,
                 external_url = :external_url,
                 sort_order = :sort_order,
                 enabled = :enabled,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'item_type' => $itemType,
            'label' => $label,
            'page_public_id' => $pagePublicId,
            'route_name' => $routeName,
            'external_url' => $externalUrl,
            'sort_order' => $sortOrder,
            'enabled' => $enabled ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $item->id,
        ]);
    }

    public function deleteItem(
        NavigationMenuItem $item,
    ): void {
        $statement = $this->pdo->prepare(
            'DELETE FROM navigation_menu_items
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $item->id,
        ]);
    }

    private static function hydrateMenu(
        array $row,
    ): NavigationMenu {
        return new NavigationMenu(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            menuKey: (string) $row['menu_key'],
            name: (string) $row['name'],
            enabled: (bool) $row['enabled'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private static function hydrateItem(
        array $row,
    ): NavigationMenuItem {
        return new NavigationMenuItem(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            menuId: (int) $row['menu_id'],
            itemType: (string) $row['item_type'],
            label: (string) $row['label'],
            pagePublicId: isset($row['page_public_id'])
                ? (string) $row['page_public_id']
                : null,
            routeName: isset($row['route_name'])
                ? (string) $row['route_name']
                : null,
            externalUrl: isset($row['external_url'])
                ? (string) $row['external_url']
                : null,
            sortOrder: (int) $row['sort_order'],
            enabled: (bool) $row['enabled'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
