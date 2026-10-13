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
    private const MANAGED_TARGETS = [
        'smi2' => 'СМИ2',
        'news_mail' => 'Новости Mail',
    ];

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

        foreach (self::MANAGED_TARGETS as $target => $label) {
            $mode = trim((string) $request->post($target . '_mode', ''));
            if ($mode !== '') {
                $this->updateManagedTarget(
                    $request,
                    $publication,
                    $target,
                    $label,
                    $mode,
                );
            }
        }

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
                '/admin/publications/' . rawurlencode($publication->publicId)
                . '/syndication-overrides?saved=1'
            );
        } catch (InvalidArgumentException $error) {
            $this->render($request, $publication, $form, $error->getMessage());
        }
    }

    private function updateManagedTarget(
        Request $request,
        Publication $publication,
        string $target,
        string $label,
        string $mode,
    ): never {
        if (!in_array($mode, ['enable', 'disable'], true)) {
            Response::text('400 Bad Request', 400);
        }
        $targets = array_values(array_filter(
            $publication->syndicationTargets,
            static fn(string $value): bool => $value !== $target,
        ));
        if ($mode === 'enable') {
            $targets[] = $target;
        }
        PublicationService::fromDatabase()->setSyndicationTargets(
            $publication->publicId,
            $targets,
        );
        AuditLog::emit(
            eventType: 'publication.partner_target_changed',
            actorUserId: $this->userId($request),
            subjectType: 'publication',
            subjectId: $publication->publicId,
            metadata: [
                'target' => $target,
                'label' => $label,
                'enabled' => $mode === 'enable',
            ],
            request: $request,
        );
        Response::redirectLocal(
            '/admin/publications/' . rawurlencode($publication->publicId)
            . '/syndication-overrides?partner_target=' . rawurlencode($target)
            . '&partner_state=' . ($mode === 'enable' ? 'enabled' : 'disabled'),
        );
    }

    private function render(
        Request $request,
        Publication $publication,
        array $overrides,
        ?string $error = null,
    ): never {
        $target = (string) $request->get('partner_target', '');
        $state = (string) $request->get('partner_state', '');
        $status = null;
        if (isset(self::MANAGED_TARGETS[$target])) {
            $status = match ($state) {
                'enabled' => 'Материал подключён к каналу «' . self::MANAGED_TARGETS[$target] . '».',
                'disabled' => 'Материал исключён из канала «' . self::MANAGED_TARGETS[$target] . '».',
                default => null,
            };
        }
        // Совместимость со ссылками, сформированными до обобщения формы.
        if ($status === null) {
            $status = match ((string) $request->get('smi2', '')) {
                'enabled' => 'Материал подключён к каналу «СМИ2».',
                'disabled' => 'Материал исключён из канала «СМИ2».',
                default => null,
            };
        }

        AdminShell::page($request, 'admin.publications.syndication_overrides', [
            'title' => 'Переопределения каналов',
            'publication' => $publication,
            'targets' => $publication->syndicationTargets,
            'overrides' => $overrides,
            'managedTargets' => array_map(
                fn(string $label, string $target): array => [
                    'id' => $target,
                    'label' => $label,
                    'enabled' => in_array($target, $publication->syndicationTargets, true),
                ],
                self::MANAGED_TARGETS,
                array_keys(self::MANAGED_TARGETS),
            ),
            'error' => $error,
            'success' => $status ?? ($request->get('saved') === '1'
                ? 'Переопределения каналов сохранены.'
                : null),
        ], 'publications');
    }

    private function publication(Request $request, string $publicId): Publication
    {
        $publication = PublicationRepository::fromDatabase()->findByPublicId($publicId);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }
        if (!PublicationOrganizationAccessService::fromDatabase()->canAccess(
            $this->userId($request),
            'publications.edit',
            $publication,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $publication;
    }

    /** @param list<string> $targets */
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
