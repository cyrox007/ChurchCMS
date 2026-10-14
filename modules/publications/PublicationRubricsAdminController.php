<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class PublicationRubricsAdminController
{
    private const SITE_KEY = 'default';

    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'publications.read',
        );

        $rubrics = PublicationRubricRepository::fromDatabase()
            ->tree(self::SITE_KEY);

        if ((string) $request->get('format', '') === 'json') {
            Response::json([
                'rubrics' => $rubrics,
            ]);
        }

        AdminShell::page(
            $request,
            'admin.publications.rubrics',
            [
                'title' => 'Рубрики публикаций',
                'rubrics' => $rubrics,
                'canManage' => AdminAuthorization::can(
                    $request,
                    'publications.edit',
                ),
                'status' => trim((string) $request->get('status', '')),
            ],
            'publication_rubrics',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'publications.edit',
        );

        try {
            PublicationRubricRepository::fromDatabase()->create(
                name: (string) $request->post('name', ''),
                parentPublicId: self::nullable(
                    $request->post('parent_public_id', null),
                ),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: self::SITE_KEY,
            );
            $this->redirect('created');
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS rubric create: ' . $error->getMessage()
            );
            $this->redirect('invalid');
        }
    }

    public function update(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'publications.edit',
        );

        try {
            PublicationRubricRepository::fromDatabase()->update(
                publicId: $publicId,
                name: (string) $request->post('name', ''),
                parentPublicId: self::nullable(
                    $request->post('parent_public_id', null),
                ),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: self::SITE_KEY,
            );
            $this->redirect('updated');
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS rubric update: ' . $error->getMessage()
            );
            $this->redirect('invalid');
        }
    }

    public function delete(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'publications.edit',
        );

        try {
            PublicationRubricRepository::fromDatabase()->delete(
                $publicId,
                self::SITE_KEY,
            );
            $this->redirect('deleted');
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS rubric delete: ' . $error->getMessage()
            );
            $this->redirect('in-use');
        }
    }

    private function redirect(string $status): never
    {
        Response::redirectLocal(
            '/admin/publications/rubrics?status=' . rawurlencode($status),
        );
    }

    private static function nullable(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
