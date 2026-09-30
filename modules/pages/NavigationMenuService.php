<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\Router;
use InvalidArgumentException;
use Throwable;

final class NavigationMenuService
{
    public const PRIMARY_KEY = 'primary';

    /**
     * @var array<string,string>
     */
    private const SYSTEM_ROUTES = [
        'home' => 'Главная',
        'publication_index' => 'Публикации',
        'gallery_index' => 'Галереи',
    ];

    public function __construct(
        private readonly NavigationMenuRepository $menus,
        private readonly PageRepository $pages,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            NavigationMenuRepository::fromDatabase(),
            PageRepository::fromDatabase(),
        );
    }

    public function ensurePrimary(
        string $siteKey = 'default',
    ): NavigationMenu {
        $menu = $this->menus->findByKey(
            self::PRIMARY_KEY,
            $siteKey,
        );

        return $menu ?? $this->menus->create(
            self::PRIMARY_KEY,
            'Основное меню',
            $siteKey,
        );
    }

    /**
     * @return array<string,string>
     */
    public function systemRoutes(): array
    {
        return self::SYSTEM_ROUTES;
    }

    /**
     * @return list<NavigationMenuItem>
     */
    public function items(
        string $menuKey = self::PRIMARY_KEY,
        string $siteKey = 'default',
    ): array {
        $menu = $this->menus->findByKey(
            $menuKey,
            $siteKey,
        );

        return $menu !== null
            ? $this->menus->items($menu->id)
            : [];
    }

    public function createItem(
        string $itemType,
        string $label,
        ?string $pagePublicId,
        ?string $routeName,
        ?string $externalUrl,
        int $sortOrder,
        bool $enabled = true,
        string $siteKey = 'default',
    ): NavigationMenuItem {
        $menu = $this->ensurePrimary($siteKey);
        $target = $this->validatedTarget(
            $itemType,
            $pagePublicId,
            $routeName,
            $externalUrl,
            $siteKey,
        );

        return $this->menus->createItem(
            $menu->id,
            $target['type'],
            self::label($label),
            $target['page_public_id'],
            $target['route_name'],
            $target['external_url'],
            self::sortOrder($sortOrder),
            $enabled,
        );
    }

    public function updateItem(
        string $publicId,
        string $itemType,
        string $label,
        ?string $pagePublicId,
        ?string $routeName,
        ?string $externalUrl,
        int $sortOrder,
        bool $enabled = true,
        string $siteKey = 'default',
    ): void {
        $menu = $this->ensurePrimary($siteKey);
        $item = $this->menus->findItem(
            trim($publicId),
            $menu->id,
        );

        if ($item === null) {
            throw new InvalidArgumentException(
                'Пункт меню не найден.'
            );
        }

        $target = $this->validatedTarget(
            $itemType,
            $pagePublicId,
            $routeName,
            $externalUrl,
            $siteKey,
        );

        $this->menus->updateItem(
            $item,
            $target['type'],
            self::label($label),
            $target['page_public_id'],
            $target['route_name'],
            $target['external_url'],
            self::sortOrder($sortOrder),
            $enabled,
        );
    }

    public function deleteItem(
        string $publicId,
        string $siteKey = 'default',
    ): void {
        $menu = $this->ensurePrimary($siteKey);
        $item = $this->menus->findItem(
            trim($publicId),
            $menu->id,
        );

        if ($item === null) {
            throw new InvalidArgumentException(
                'Пункт меню не найден.'
            );
        }

        $this->menus->deleteItem($item);
    }

    /**
     * @return list<array{label:string,url:string}>
     */
    public function publicNavigation(
        string $menuKey = self::PRIMARY_KEY,
        string $siteKey = 'default',
    ): array {
        $menu = $this->menus->findByKey(
            $menuKey,
            $siteKey,
        );

        if ($menu === null || !$menu->enabled) {
            return [];
        }

        $navigation = [];

        foreach ($this->menus->items($menu->id) as $item) {
            if (!$item->enabled) {
                continue;
            }

            $url = $this->publicUrl(
                $item,
                $siteKey,
            );

            if ($url === null) {
                continue;
            }

            $navigation[] = [
                'label' => $item->label,
                'url' => $url,
            ];
        }

        return $navigation;
    }

    /**
     * @return array{
     *     type:string,
     *     page_public_id:?string,
     *     route_name:?string,
     *     external_url:?string
     * }
     */
    private function validatedTarget(
        string $itemType,
        ?string $pagePublicId,
        ?string $routeName,
        ?string $externalUrl,
        string $siteKey,
    ): array {
        $itemType = strtolower(trim($itemType));

        return match ($itemType) {
            'page' => $this->pageTarget(
                $pagePublicId,
                $siteKey,
            ),
            'route' => self::routeTarget(
                $routeName,
            ),
            'external' => self::externalTarget(
                $externalUrl,
            ),
            default => throw new InvalidArgumentException(
                'Некорректный тип пункта меню.'
            ),
        };
    }

    /**
     * @return array{
     *     type:string,
     *     page_public_id:string,
     *     route_name:null,
     *     external_url:null
     * }
     */
    private function pageTarget(
        ?string $publicId,
        string $siteKey,
    ): array {
        $publicId = trim((string) $publicId);
        $page = $this->pages->findByPublicId(
            $publicId,
            $siteKey,
        );

        if ($page === null) {
            throw new InvalidArgumentException(
                'Страница для пункта меню не найдена.'
            );
        }

        return [
            'type' => 'page',
            'page_public_id' => $page->publicId,
            'route_name' => null,
            'external_url' => null,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     page_public_id:null,
     *     route_name:string,
     *     external_url:null
     * }
     */
    private static function routeTarget(
        ?string $routeName,
    ): array {
        $routeName = trim((string) $routeName);

        if (!array_key_exists(
            $routeName,
            self::SYSTEM_ROUTES,
        )) {
            throw new InvalidArgumentException(
                'Системный раздел не разрешён для меню.'
            );
        }

        return [
            'type' => 'route',
            'page_public_id' => null,
            'route_name' => $routeName,
            'external_url' => null,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     page_public_id:null,
     *     route_name:null,
     *     external_url:string
     * }
     */
    private static function externalTarget(
        ?string $url,
    ): array {
        $url = trim((string) $url);

        if (
            $url === ''
            || strlen($url) > 1000
        ) {
            throw new InvalidArgumentException(
                'Укажите внешний HTTPS URL.'
            );
        }

        $parts = parse_url($url);

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'Внешняя ссылка меню должна использовать HTTPS без учётных данных.'
            );
        }

        return [
            'type' => 'external',
            'page_public_id' => null,
            'route_name' => null,
            'external_url' => $url,
        ];
    }

    private function publicUrl(
        NavigationMenuItem $item,
        string $siteKey,
    ): ?string {
        if (
            $item->itemType === 'page'
            && $item->pagePublicId !== null
        ) {
            $page = $this->pages->findByPublicId(
                $item->pagePublicId,
                $siteKey,
            );

            return $page !== null
                && $page->status === PageStatus::Published
                    ? '/pages' . $page->path
                    : null;
        }

        if (
            $item->itemType === 'route'
            && $item->routeName !== null
            && isset(self::SYSTEM_ROUTES[$item->routeName])
        ) {
            try {
                return Router::getInstance()->url(
                    $item->routeName,
                );
            } catch (Throwable) {
                return null;
            }
        }

        if (
            $item->itemType === 'external'
            && $item->externalUrl !== null
        ) {
            try {
                return self::externalTarget(
                    $item->externalUrl,
                )['external_url'];
            } catch (InvalidArgumentException) {
                return null;
            }
        }

        return null;
    }

    private static function label(string $value): string
    {
        $value = trim($value);

        if (
            $value === ''
            || self::length($value) > 255
        ) {
            throw new InvalidArgumentException(
                'Название пункта меню обязательно и не должно превышать 255 символов.'
            );
        }

        return $value;
    }

    private static function sortOrder(int $value): int
    {
        if ($value < -100000 || $value > 100000) {
            throw new InvalidArgumentException(
                'Порядок пункта меню выходит за допустимый диапазон.'
            );
        }

        return $value;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
