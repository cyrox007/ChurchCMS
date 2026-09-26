<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use Stringable;

final class ThemeContext
{
    public function __construct(
        private readonly ThemeRenderer $renderer,
        private readonly string $themeId,
    ) {
    }

    public function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (!is_scalar($value) && !$value instanceof Stringable) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function partial(string $logicalName, array $data = []): string
    {
        return $this->renderer->capture($logicalName, $data);
    }

    public function component(string $logicalName, array $props = [], array $slots = []): string
    {
        return $this->renderer->capture($logicalName, [
            'props' => $props,
            'slots' => $slots,
        ]);
    }

    public function asset(string $path): string
    {
        return $this->renderer->assetUrl($path);
    }

    public function route(string $name, array $params = []): string
    {
        return Router::getInstance()->url($name, $params);
    }

    public function csrfInput(): string
    {
        return Csrf::input();
    }

    public function publicFormToken(string $scope): string
    {
        return PublicFormToken::issue($scope);
    }

    public function themeId(): string
    {
        return $this->themeId;
    }
}
