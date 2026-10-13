<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class RutubePublishingChannelAdapter implements
    ChannelAdapter,
    ChannelConnectionTester,
    ChannelCredentialsOptional,
    ChannelOperationalStatusProvider
{
    public function __construct(
        private readonly RutubeChannelAdapter $inbound = new RutubeChannelAdapter(),
        private readonly RutubeUploadTransport $upload = new RutubeUnavailableUploadTransport(),
    ) {
    }

    public function providerId(): string
    {
        return $this->inbound->providerId();
    }

    public function label(): string
    {
        return $this->inbound->label();
    }

    public function capabilities(): array
    {
        return array_values(array_unique([
            ...$this->inbound->capabilities(),
            ChannelCapability::PUBLISH_VIDEO,
        ]));
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        return $this->inbound->testConnection(
            $targetRef,
            $credentials,
            $settings,
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        $validation = RutubeVideoPolicy::validate($item);
        if (!$validation['success']) {
            return new ChannelPublishResult(
                success: false,
                error: $validation['error'],
                retryable: false,
            );
        }

        $result = $this->upload->upload(
            $connection,
            $credentials,
            $item,
            $validation['video'],
        );

        return new ChannelPublishResult(
            success: $result->success,
            remoteId: $result->remoteId,
            remoteUrl: $result->remoteUrl,
            error: $result->error,
            retryable: $result->retryable,
        );
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        return $this->inbound->pull(
            $connection,
            $credentials,
            $cursor,
            $limit,
        );
    }

    public function operationalStatus(): array
    {
        return [
            'level' => $this->upload->isReady() ? 'success' : 'warning',
            'inbound_ready' => true,
            'outbound_ready' => $this->upload->isReady(),
            'code' => $this->upload->diagnosticCode(),
            'message' => $this->upload->isReady()
                ? 'Входящая синхронизация и исходящая загрузка Rutube готовы.'
                : $this->upload->diagnosticMessage(),
        ];
    }
}
