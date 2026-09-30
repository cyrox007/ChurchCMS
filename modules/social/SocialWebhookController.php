<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class SocialWebhookController
{
    public function receive(
        Request $request,
        string $connectionPublicId,
    ): never {
        $rawBody = $request->rawBody();
        if ($rawBody === null) {
            $status = $request->bodyError()
                === 'request_body_too_large'
                ? 413
                : 400;

            Response::json(
                ['error' => 'webhook_rejected'],
                $status,
            );
        }

        $webhookRequest = new ChannelWebhookRequest(
            rawBody: $rawBody,
            headers: $request->headers(),
        );

        try {
            $result = ChannelWebhookService::fromDatabase()
                ->receive(
                    $connectionPublicId,
                    $webhookRequest,
                );

            Response::raw(
                $result->body,
                $result->status,
                $result->contentType,
            );
        } catch (ChannelWebhookException $error) {
            error_log(
                'ChurchCMS webhook внешнего канала: '
                . $error->getMessage()
            );

            Response::json(
                ['error' => 'webhook_rejected'],
                $error->httpStatus,
            );
        }
    }
}
