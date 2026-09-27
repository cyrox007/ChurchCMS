<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;

final class FederationApiController
{
    public function meta(Request $request): never
    {
        $instanceId = trim(
            (string) Config::get(
                'federation.instance_id',
                '',
            )
        );

        if (
            preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $instanceId,
            ) !== 1
        ) {
            ApiResponse::error(
                'federation_not_ready',
                'Federation identity is not configured.',
                503,
            );
        }

        $root = OrganizationRepository::fromDatabase()
            ->siteRoot('default');

        if ($root === null) {
            ApiResponse::error(
                'federation_not_ready',
                'Root organization is not configured.',
                503,
            );
        }

        ApiResponse::success(
            [
                'protocol' => 'churchcms-federation-v1',
                'instance_id' => $instanceId,
                'site_key' => $root->siteKey,
                'profile' => (string) Config::get(
                    'site.profile',
                    'organization',
                ),
                'organization' => [
                    'id' => $root->publicId,
                    'type' => $root->type,
                    'name' => $root->name,
                ],
                'capabilities' => [
                    'public-api-v1',
                    'partner-api-v1',
                    'organization-federation-v1',
                ],
            ],
            cacheSeconds: 300,
        );
    }
}
