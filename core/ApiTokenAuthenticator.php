<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class ApiTokenAuthenticator
{
    /**
     * @return array{id:string,scopes:list<string>,origins:list<string>}|null
     */
    public function authenticate(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $candidateHash = hash('sha256', $token);
        $configured = Config::get('api.partner_tokens', []);

        if (!is_array($configured)) {
            return null;
        }

        foreach ($configured as $id => $entry) {
            if (!is_string($id) || !is_array($entry) || ($entry['enabled'] ?? true) !== true) {
                continue;
            }

            $storedHash = strtolower(trim((string) ($entry['hash'] ?? '')));
            if (preg_match('/^[a-f0-9]{64}$/D', $storedHash) !== 1) {
                continue;
            }

            if (!hash_equals($storedHash, $candidateHash)) {
                continue;
            }

            $scopes = array_values(array_filter(
                is_array($entry['scopes'] ?? null) ? $entry['scopes'] : [],
                static fn(mixed $scope): bool => is_string($scope) && preg_match('/^[a-z][a-z0-9_.:-]{1,63}$/D', $scope) === 1,
            ));

            $origins = array_values(array_filter(
                is_array($entry['origins'] ?? null) ? $entry['origins'] : [],
                static fn(mixed $origin): bool => is_string($origin) && self::isSafeOrigin($origin),
            ));

            return [
                'id' => $id,
                'scopes' => $scopes,
                'origins' => $origins,
            ];
        }

        return null;
    }

    private static function isSafeOrigin(string $origin): bool
    {
        $parts = parse_url($origin);
        if (!is_array($parts)) {
            return false;
        }

        return in_array($parts['scheme'] ?? null, ['https', 'http'], true)
            && isset($parts['host'])
            && !isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment']);
    }
}
