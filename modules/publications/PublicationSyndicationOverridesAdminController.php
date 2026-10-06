<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class PublicationSyndicationOverridesAdminController
{
    public function edit(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'publications.edit');
        $publication = $this->publication($request, $publicId);

        $this->render(
            $request,
            $publication,
            PublicationSyndicationOverrideRepository::fromDatabase()
                ->forPublication($publication->id),
        );
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'publications.edit');
        $publication = $this->publication($request, $publicId);
        $form = $this->form($request, $publication->syndicationTargets);

        try {
            $repository = PublicationSyndicationOverrideRepository::fromDatabase();
            foreach ($publication->syndicationTargets as $target) {
                $values = $form[$target] ?? [];
                $repository->save(
                    $publication->id,
                    $target,
                    $values['title'] ?? null,
                    $values['excerpt'] ?? null,
                    $values['image_url'] ?? null,
                    $values['image_mime'] ?? null,
                );
            }

            $this->audit($request, $publication->publicId);
            Response::redirectLocal(
                '/admin/publications/'
                . rawurlencode($publication->publicId)
                . '/syndication-overrides?saved=1'
            );
        } catch (InvalidArgumentException $error) {
            $this->render(
                $request,
                $publication,
                $form,
                $error->getMessage(),
            );
        }
    }

    private function render(
        Request $request,
        Publication $publication,
        array $overrides,
        ?string $error = null,
    ): never {
        AdminShell::page($request, 'admin.publications.syndication_overrides', [
            'title' => 'Переопределения каналов',
            'publication' => $publication,
            'targets' => $publication->syndicationTargets,
            'overrides' => $overrides,
            'error' => $error,
            'success' => $request->get('saved') === '1'
                ? 'Переопределения каналов сохранены.'
                : null,
        ], 'publications');
    }

    private function publication(Request $request, string $publicId): Publication
    {
        $publication = PublicationRepository::fromDatabase()->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }

        if (
            !PublicationOrganizationAccessService::fromDatabase()->canAccess(
                $this->userId($request),
                'publications.edit',
                $publication,
            )
        ) {
            Response::text('403 Forbidden', 403);
        }

        return $publication;
    }

    /**
     * @param list<string> $targets
     * @return array<string,array{title:string,excerpt:string,image_url:string,image_mime:string}>
     */
    private function form(Request $request, array $targets): array
    {
        $titles = $this->arrayPost($request, 'title');
        $excerpts = $this->arrayPost($request, 'excerpt');
        $imageUrls = $this->arrayPost($request, 'image_url');
        $imageMimes = $this->arrayPost($request, 'image_mime');

        $result = [];
        foreach ($targets as $target) {
            $result[$target] = [
                'title' => trim((string) ($titles[$target] ?? '')),
                'excerpt' => trim((string) ($excerpts[$target] ?? '')),
                'image_url' => trim((string) ($imageUrls[$target] ?? '')),
                'image_mime' => trim((string) ($imageMimes[$target] ?? '')),
            ];
        }

        return $result;
    }

    /** @return array<string,mixed> */
    private function arrayPost(Request $request, string $key): array
    {
        $value = $request->post($key, []);
        return is_array($value) ? $value : [];
    }

    private function userId(Request $request): int
    {
        $user = $request->attribute('admin.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        if ($userId <= 0) {
            Response::text('403 Forbidden', 403);
        }

        return $userId;
    }

    private function audit(Request $request, string $publicId): void
    {
        AuditLog::emit(
            eventType: 'publication.syndication_overrides_updated',
            actorUserId: $this->userId($request),
            subjectType: 'publication',
            subjectId: $publicId,
            request: $request,
        );
    }
}
