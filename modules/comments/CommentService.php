<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\PageCache;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Publications\Publication;
use InvalidArgumentException;
use PDO;

final class CommentService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function submit(
        Publication $publication,
        string $displayName,
        ?string $email,
        string $bodyText,
    ): string {
        if (Config::get('comments.enabled', true) !== true || !$publication->commentsEnabled) {
            throw new InvalidArgumentException('Comments are disabled for this publication.');
        }

        $displayName = trim(strip_tags($displayName));
        $email = $email !== null ? trim($email) : null;
        $bodyText = self::normalizePlainText($bodyText);

        $nameLength = function_exists('mb_strlen')
            ? mb_strlen($displayName, 'UTF-8')
            : strlen($displayName);
        if ($nameLength < 2 || $nameLength > 100) {
            throw new InvalidArgumentException('Display name must contain 2-100 characters.');
        }

        if ($email === '') {
            $email = null;
        }
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $maxLength = max(100, min(10000, (int) Config::get('comments.max_length', 4000)));
        $bodyLength = function_exists('mb_strlen')
            ? mb_strlen($bodyText, 'UTF-8')
            : strlen($bodyText);

        if ($bodyLength < 2 || $bodyLength > $maxLength) {
            throw new InvalidArgumentException("Comment must contain 2-{$maxLength} characters.");
        }

        $moderation = (string) Config::get('comments.moderation', 'premoderated');
        $status = $moderation === 'open'
            ? CommentStatus::Approved
            : CommentStatus::Pending;

        $publicId = Uuid::v4();
        $statement = $this->pdo->prepare(
            'INSERT INTO publication_comments (
                public_id, publication_id, status, display_name, email, body_text,
                created_at, moderated_at, moderator_user_id
             ) VALUES (
                :public_id, :publication_id, :status, :display_name, :email, :body_text,
                :created_at, NULL, NULL
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'publication_id' => $publication->id,
            'status' => $status->value,
            'display_name' => $displayName,
            'email' => $email,
            'body_text' => $bodyText,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($status === CommentStatus::Approved) {
            PageCache::bumpVersion();
        }

        return $publicId;
    }

    public function moderate(
        string $publicId,
        CommentStatus $status,
        int $moderatorUserId,
    ): void {
        if ($moderatorUserId <= 0) {
            throw new InvalidArgumentException('Invalid moderator.');
        }

        if (!in_array($status, [CommentStatus::Approved, CommentStatus::Rejected, CommentStatus::Spam], true)) {
            throw new InvalidArgumentException('Invalid moderation target status.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE publication_comments
             SET status = :status, moderated_at = :moderated_at, moderator_user_id = :moderator_user_id
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'status' => $status->value,
            'moderated_at' => gmdate('Y-m-d H:i:s'),
            'moderator_user_id' => $moderatorUserId,
            'public_id' => $publicId,
        ]);

        PageCache::bumpVersion();
    }

    private static function normalizePlainText(string $text): string
    {
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
