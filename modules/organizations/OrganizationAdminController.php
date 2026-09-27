<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;
use Throwable;

final class OrganizationAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'organizations.manage',
        );

        $repository = OrganizationRepository::fromDatabase();

        AdminShell::page(
            $request,
            'admin.organizations.index',
            [
                'title' => 'Церковная структура',
                'units' => $repository->tree(),
                'root' => $repository->siteRoot(),
                'typeLabels' => OrganizationTypeCatalog::all(),
                'organizationStatus' => self::status($request),
            ],
            'organizations',
        );
    }

    public function createForm(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'organizations.manage',
        );

        $parent = trim((string) $request->get('parent', ''));

        $this->editor(
            $request,
            null,
            self::emptyForm($parent),
            null,
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'organizations.manage',
        );

        $form = self::form($request);

        try {
            $publicId = OrganizationService::fromDatabase()
                ->create(
                    name: $form['name'],
                    type: $form['type'],
                    parentPublicId: $form['parent_public_id'],
                    slug: $form['slug'],
                    shortName: $form['short_name'],
                    legalName: $form['legal_name'],
                    descriptionInput: $form['description'],
                    sortOrder: $form['sort_order'],
                );

            AuditLog::emit(
                eventType: 'organization.created',
                actorUserId: self::actorId($request),
                subjectType: 'organization',
                subjectId: $publicId,
                metadata: [
                    'unit_type' => $form['type'],
                    'parent_public_id' =>
                        $form['parent_public_id'],
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/organizations/'
                . rawurlencode($publicId)
                . '?status=created',
            );
        } catch (InvalidArgumentException $error) {
            $this->editor(
                $request,
                null,
                $form,
                $error->getMessage(),
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS organization create: '
                . $error->getMessage()
            );
            $this->editor(
                $request,
                null,
                $form,
                'Не удалось создать организацию. Повторите попытку.',
            );
        }
    }

    public function edit(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'organizations.manage',
        );

        $unit = OrganizationRepository::fromDatabase()
            ->findByPublicId($publicId);

        if ($unit === null) {
            Response::text('404 Not Found', 404);
        }

        $this->editor(
            $request,
            $unit,
            self::formFromUnit($unit),
            null,
        );
    }

    public function update(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'organizations.manage',
        );

        $repository = OrganizationRepository::fromDatabase();
        $unit = $repository->findByPublicId($publicId);

        if ($unit === null) {
            Response::text('404 Not Found', 404);
        }

        $form = self::form($request);

        try {
            OrganizationService::fromDatabase()->update(
                publicId: $publicId,
                name: $form['name'],
                type: $form['type'],
                parentPublicId: $form['parent_public_id'],
                slug: $form['slug'],
                shortName: $form['short_name'],
                legalName: $form['legal_name'],
                descriptionInput: $form['description'],
                sortOrder: $form['sort_order'],
            );

            AuditLog::emit(
                eventType: 'organization.updated',
                actorUserId: self::actorId($request),
                subjectType: 'organization',
                subjectId: $publicId,
                metadata: [
                    'unit_type' => $form['type'],
                    'parent_public_id' =>
                        $form['parent_public_id'],
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/organizations/'
                . rawurlencode($publicId)
                . '?status=updated',
            );
        } catch (InvalidArgumentException $error) {
            $this->editor(
                $request,
                $unit,
                $form,
                $error->getMessage(),
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS organization update: '
                . $error->getMessage()
            );
            $this->editor(
                $request,
                $unit,
                $form,
                'Не удалось сохранить изменения. Повторите попытку.',
            );
        }
    }

    public function archive(
        Request $request,
        string $publicId,
    ): never {
        $this->setArchived(
            $request,
            $publicId,
            true,
        );
    }

    public function restore(
        Request $request,
        string $publicId,
    ): never {
        $this->setArchived(
            $request,
            $publicId,
            false,
        );
    }

    private function setArchived(
        Request $request,
        string $publicId,
        bool $archive,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'organizations.manage',
        );

        try {
            $service = OrganizationService::fromDatabase();

            if ($archive) {
                $service->archiveSubtree($publicId);
            } else {
                $service->restoreSubtree($publicId);
            }

            AuditLog::emit(
                eventType: $archive
                    ? 'organization.archived'
                    : 'organization.restored',
                actorUserId: self::actorId($request),
                subjectType: 'organization',
                subjectId: $publicId,
                metadata: [
                    'subtree' => true,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/organizations?status='
                . ($archive ? 'archived' : 'restored'),
            );
        } catch (InvalidArgumentException $error) {
            Response::redirectLocal(
                '/admin/organizations?status='
                . rawurlencode(
                    $archive
                        ? 'archive-failed'
                        : 'restore-failed',
                ),
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS organization status: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/organizations?status=operation-failed',
            );
        }
    }

    private function editor(
        Request $request,
        ?OrganizationUnit $unit,
        array $form,
        ?string $error,
    ): never {
        $repository = OrganizationRepository::fromDatabase();
        $units = $repository->tree();
        $root = $repository->siteRoot();

        $excluded = [];
        if ($unit !== null) {
            foreach ($repository->subtree($unit) as $child) {
                $excluded[$child->id] = true;
            }
        }

        $parents = array_values(array_filter(
            $units,
            static fn(OrganizationUnit $candidate): bool =>
                !isset($excluded[$candidate->id])
                && $candidate->status === 'active',
        ));

        AdminShell::page(
            $request,
            'admin.organizations.editor',
            [
                'title' => $unit === null
                    ? 'Новая организация'
                    : 'Редактирование организации',
                'unit' => $unit,
                'root' => $root,
                'parents' => $parents,
                'typeLabels' => OrganizationTypeCatalog::all(),
                'form' => $form,
                'error' => $error,
                'organizationStatus' => self::status($request),
            ],
            'organizations',
        );
    }

    /**
     * @return array{
     *     name:string,
     *     type:string,
     *     parent_public_id:?string,
     *     slug:string,
     *     short_name:?string,
     *     legal_name:?string,
     *     description:string,
     *     sort_order:int
     * }
     */
    private static function form(Request $request): array
    {
        $parent = trim(
            (string) $request->post(
                'parent_public_id',
                '',
            )
        );

        return [
            'name' => trim(
                (string) $request->post('name', '')
            ),
            'type' => trim(
                (string) $request->post(
                    'type',
                    'church_organization',
                )
            ),
            'parent_public_id' =>
                $parent !== '' ? $parent : null,
            'slug' => trim(
                (string) $request->post('slug', '')
            ),
            'short_name' => self::optional(
                $request->post('short_name')
            ),
            'legal_name' => self::optional(
                $request->post('legal_name')
            ),
            'description' => trim(
                (string) $request->post(
                    'description',
                    '',
                )
            ),
            'sort_order' => (int) $request->post(
                'sort_order',
                0,
            ),
        ];
    }

    /**
     * @return array{
     *     name:string,
     *     type:string,
     *     parent_public_id:?string,
     *     slug:string,
     *     short_name:?string,
     *     legal_name:?string,
     *     description:string,
     *     sort_order:int
     * }
     */
    private static function formFromUnit(
        OrganizationUnit $unit,
    ): array {
        $parentPublicId = null;

        if ($unit->parentId !== null) {
            $parent = OrganizationRepository::fromDatabase()
                ->findById(
                    $unit->parentId,
                    $unit->siteKey,
                );
            $parentPublicId = $parent?->publicId;
        }

        return [
            'name' => $unit->name,
            'type' => $unit->type,
            'parent_public_id' => $parentPublicId,
            'slug' => $unit->slug,
            'short_name' => $unit->shortName,
            'legal_name' => $unit->legalName,
            'description' => self::editorText(
                $unit->descriptionHtml,
            ),
            'sort_order' => $unit->sortOrder,
        ];
    }

    /**
     * @return array{
     *     name:string,
     *     type:string,
     *     parent_public_id:?string,
     *     slug:string,
     *     short_name:?string,
     *     legal_name:?string,
     *     description:string,
     *     sort_order:int
     * }
     */
    private static function emptyForm(
        string $parentPublicId = '',
    ): array {
        return [
            'name' => '',
            'type' => 'parish',
            'parent_public_id' =>
                $parentPublicId !== ''
                    ? $parentPublicId
                    : null,
            'slug' => '',
            'short_name' => null,
            'legal_name' => null,
            'description' => '',
            'sort_order' => 0,
        ];
    }

    private static function optional(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private static function editorText(string $html): string
    {
        $text = preg_replace(
            '#<br\s*/?>#i',
            "\n",
            $html,
        );
        $text = preg_replace(
            '#</p>\s*<p[^>]*>#i',
            "\n\n",
            (string) $text,
        );
        $text = strip_tags((string) $text);

        return html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
    }

    private static function actorId(
        Request $request,
    ): ?int {
        $user = $request->attribute('admin.user');
        $id = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array{kind:string,title:string,message:string}|null
     */
    private static function status(
        Request $request,
    ): ?array {
        return match ((string) $request->get('status', '')) {
            'created' => [
                'kind' => 'success',
                'title' => 'Организация создана',
                'message' => 'Новый элемент добавлен в церковную структуру.',
            ],
            'updated' => [
                'kind' => 'success',
                'title' => 'Изменения сохранены',
                'message' => 'Название, положение и описание структуры обновлены.',
            ],
            'archived' => [
                'kind' => 'success',
                'title' => 'Подразделение архивировано',
                'message' => 'Элемент и его дочерние подразделения скрыты из активной структуры.',
            ],
            'restored' => [
                'kind' => 'success',
                'title' => 'Подразделение восстановлено',
                'message' => 'Элемент и его дочерние подразделения снова активны.',
            ],
            'archive-failed' => [
                'kind' => 'error',
                'title' => 'Архивирование недоступно',
                'message' => 'Корневую организацию сайта архивировать нельзя.',
            ],
            'restore-failed' => [
                'kind' => 'error',
                'title' => 'Восстановление недоступно',
                'message' => 'Сначала восстановите родительскую организацию.',
            ],
            'operation-failed' => [
                'kind' => 'error',
                'title' => 'Операция не выполнена',
                'message' => 'Повторите попытку. Подробность записана в журнал сервера.',
            ],
            default => null,
        };
    }
}
