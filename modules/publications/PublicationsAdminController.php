<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;
use InvalidArgumentException;

final class PublicationsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.read');

        ThemeRenderer::fromConfig()->page('admin.publications.index', [
            'title' => 'Публикации',
            'siteName' => 'ChurchCMS',
            'publications' => PublicationRepository::fromDatabase()->adminList(),
            'canCreate' => AdminAuthorization::can($request, 'publications.create'),
            'canEdit' => AdminAuthorization::can($request, 'publications.edit'),
            'canPublish' => AdminAuthorization::can($request, 'publications.publish'),
        ]);
    }

    public function createForm(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.create');

        $this->renderEditor(
            request: $request,
            publication: null,
            form: $this->emptyForm(),
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

        try {
            $publicId = PublicationService::fromDatabase()->createDraft(
                title: $form['title'],
                slug: $form['slug'],
                type: $type,
                excerpt: $form['excerpt'],
                bodyHtml: $form['body'],
                authorName: $form['author_name'],
                syndicationTargets: $this->targetsForRequest($request, []),
                commentsEnabled: $form['comments_enabled'],
            );

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

        $form = $this->formFromRequest($request);
        $type = PublicationType::tryFrom($form['type']);

        if ($type === null) {
            $this->renderEditor($request, $publication, $form, 'Выберите тип публикации.');
        }

        try {
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
            );

            $this->audit($request, 'publication.updated', $publicId);
            Response::redirectLocal('/admin/publications/' . rawurlencode($publicId) . '?saved=1');
        } catch (InvalidArgumentException) {
            $this->renderEditor(
                $request,
                $publication,
                $form,
                'Не удалось сохранить. Возможно, такой адрес материала уже используется.',
            );
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'publications.publish');

        if (PublicationRepository::fromDatabase()->findByPublicId($publicId) === null) {
            Response::text('404 Not Found', 404);
        }

        PublicationService::fromDatabase()->publish($publicId);
        $this->audit($request, 'publication.published', $publicId);

        Response::redirectLocal('/admin/publications/' . rawurlencode($publicId) . '?saved=1');
    }

    public function withdraw(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'publications.publish');

        if (PublicationRepository::fromDatabase()->findByPublicId($publicId) === null) {
            Response::text('404 Not Found', 404);
        }

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
        ThemeRenderer::fromConfig()->page('admin.publications.editor', [
            'title' => $publication === null ? 'Новая публикация' : 'Редактирование публикации',
            'siteName' => 'ChurchCMS',
            'publication' => $publication,
            'form' => $form,
            'error' => $error,
            'success' => $success,
            'canPublish' => AdminAuthorization::can($request, 'publications.publish'),
            'canSyndicate' => AdminAuthorization::can($request, 'publications.syndicate'),
        ]);
    }

    private function formFromRequest(Request $request): array
    {
        return [
            'type' => (string) $request->post('type', PublicationType::News->value),
            'title' => trim((string) $request->post('title', '')),
            'excerpt' => trim((string) $request->post('excerpt', '')),
            'body' => trim((string) $request->post('body', '')),
            'author_name' => trim((string) $request->post('author_name', '')),
            'slug' => trim((string) $request->post('slug', '')),
            'comments_enabled' => $request->post('comments_enabled') === '1',
        ];
    }

    private function formFromPublication(Publication $publication): array
    {
        return [
            'type' => $publication->type->value,
            'title' => $publication->title,
            'excerpt' => $publication->excerpt,
            'body' => self::editorText($publication->bodyHtml),
            'author_name' => $publication->authorName ?? '',
            'slug' => $publication->slug,
            'comments_enabled' => $publication->commentsEnabled,
        ];
    }

    private function emptyForm(): array
    {
        return [
            'type' => PublicationType::News->value,
            'title' => '',
            'excerpt' => '',
            'body' => '',
            'author_name' => '',
            'slug' => '',
            'comments_enabled' => false,
        ];
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

    private static function editorText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p\s*>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }
}
