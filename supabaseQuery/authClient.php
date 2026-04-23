<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

if (!function_exists('supabaseAuthEnsureEnvLoaded')) {
    function supabaseAuthEnsureEnvLoaded(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        if (!isset($_ENV['SUPABASE_URL']) || !isset($_ENV['SUPABASE_KEY'])) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }

        $loaded = true;
    }
}

if (!function_exists('supabaseAuthBaseUrl')) {
    function supabaseAuthBaseUrl(): string
    {
        supabaseAuthEnsureEnvLoaded();
        return rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/auth/v1';
    }
}

if (!function_exists('supabaseAuthApiKey')) {
    function supabaseAuthApiKey(): string
    {
        supabaseAuthEnsureEnvLoaded();
        return $_ENV['SUPABASE_KEY'] ?? '';
    }
}

if (!function_exists('supabaseAuthRequest')) {
    function supabaseAuthRequest(string $method, string $endpoint, ?array $payload = null, ?string $bearerToken = null): array
    {
        $url = preg_match('#^https?://#', $endpoint) ? $endpoint : supabaseAuthBaseUrl() . '/' . ltrim($endpoint, '/');
        $headers = [
            'apikey: ' . supabaseAuthApiKey(),
            'Accept: application/json',
        ];

        if ($bearerToken !== null && $bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response ?: '', true);

        return [
            'ok' => $curlError === '' && $statusCode >= 200 && $statusCode < 300,
            'code' => $statusCode,
            'error' => $curlError,
            'data' => $data,
            'raw' => $response ?: '',
        ];
    }
}

if (!function_exists('supabaseAuthErrorMessage')) {
    function supabaseAuthErrorMessage(array $result, string $fallback): string
    {
        if (!empty($result['error'])) {
            return $fallback . ' (' . $result['error'] . ')';
        }

        if (is_array($result['data'])) {
            $details = $result['data']['msg']
                ?? $result['data']['message']
                ?? $result['data']['error_description']
                ?? $result['data']['error']
                ?? null;

            if (is_string($details) && $details !== '') {
                return $fallback . ' (' . $details . ')';
            }
        }

        return $fallback;
    }
}

if (!function_exists('supabaseAuthSignInWithPassword')) {
    function supabaseAuthSignInWithPassword(string $email, string $password): array
    {
        return supabaseAuthRequest(
            'POST',
            'token?grant_type=password',
            [
                'email' => $email,
                'password' => $password,
            ]
        );
    }
}

if (!function_exists('supabaseAuthRefreshSession')) {
    function supabaseAuthRefreshSession(string $refreshToken): array
    {
        return supabaseAuthRequest(
            'POST',
            'token?grant_type=refresh_token',
            ['refresh_token' => $refreshToken]
        );
    }
}

if (!function_exists('supabaseAuthGetUser')) {
    function supabaseAuthGetUser(string $accessToken): array
    {
        return supabaseAuthRequest('GET', 'user', null, $accessToken);
    }
}

if (!function_exists('supabaseAuthLogout')) {
    function supabaseAuthLogout(string $accessToken): array
    {
        return supabaseAuthRequest('POST', 'logout?scope=local', null, $accessToken);
    }
}

if (!function_exists('supabaseAuthCreateFactor')) {
    function supabaseAuthCreateFactor(string $accessToken, array $payload): array
    {
        return supabaseAuthRequest('POST', 'factors', $payload, $accessToken);
    }
}

if (!function_exists('supabaseAuthCreateFactorChallenge')) {
    function supabaseAuthCreateFactorChallenge(string $accessToken, string $factorId, array $payload = []): array
    {
        return supabaseAuthRequest('POST', 'factors/' . rawurlencode($factorId) . '/challenge', $payload === [] ? null : $payload, $accessToken);
    }
}

if (!function_exists('supabaseAuthVerifyFactorChallenge')) {
    function supabaseAuthVerifyFactorChallenge(string $accessToken, string $factorId, string $challengeId, string $code): array
    {
        return supabaseAuthRequest(
            'POST',
            'factors/' . rawurlencode($factorId) . '/verify',
            [
                'challenge_id' => $challengeId,
                'code' => $code,
            ],
            $accessToken
        );
    }
}

if (!function_exists('supabaseAuthChallengeAndVerifyFactor')) {
    function supabaseAuthChallengeAndVerifyFactor(string $accessToken, string $factorId, string $code): array
    {
        $headers = [
            'apikey: ' . supabaseAuthApiKey(),
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $challengeUrl = supabaseAuthBaseUrl() . '/factors/' . rawurlencode($factorId) . '/challenge';
        $verifyUrl = supabaseAuthBaseUrl() . '/factors/' . rawurlencode($factorId) . '/verify';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        curl_setopt($ch, CURLOPT_URL, $challengeUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');

        $challengeRaw = curl_exec($ch);
        $challengeError = curl_error($ch);
        $challengeCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $challengeData = json_decode($challengeRaw ?: '', true);

        $challengeId = (string) ($challengeData['id'] ?? $challengeData['challenge_id'] ?? '');
        if ($challengeError !== '' || $challengeCode < 200 || $challengeCode >= 300 || $challengeId === '') {
            curl_close($ch);
            return [
                'ok' => false,
                'code' => $challengeCode,
                'error' => $challengeError,
                'data' => $challengeData,
                'raw' => $challengeRaw ?: '',
            ];
        }

        curl_setopt($ch, CURLOPT_URL, $verifyUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'challenge_id' => $challengeId,
            'code' => $code,
        ]));

        $verifyRaw = curl_exec($ch);
        $verifyError = curl_error($ch);
        $verifyCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $verifyData = json_decode($verifyRaw ?: '', true);

        return [
            'ok' => $verifyError === '' && $verifyCode >= 200 && $verifyCode < 300,
            'code' => $verifyCode,
            'error' => $verifyError,
            'data' => $verifyData,
            'raw' => $verifyRaw ?: '',
        ];
    }
}

if (!function_exists('supabaseAuthDeleteFactor')) {
    function supabaseAuthDeleteFactor(string $accessToken, string $factorId): array
    {
        return supabaseAuthRequest('DELETE', 'factors/' . rawurlencode($factorId), null, $accessToken);
    }
}

if (!function_exists('supabaseAuthAdminCreateUser')) {
    function supabaseAuthAdminCreateUser(string $email, string $password, array $userMetadata = [], bool $emailConfirm = true): array
    {
        $payload = [
            'email' => $email,
            'password' => $password,
            'email_confirm' => $emailConfirm,
        ];

        if ($userMetadata !== []) {
            $payload['user_metadata'] = $userMetadata;
            $payload['app_metadata'] = [
                'type' => $userMetadata['type'] ?? null,
                'username' => $userMetadata['username'] ?? null,
            ];
        }

        return supabaseAuthRequest('POST', 'admin/users', $payload, supabaseAuthApiKey());
    }
}

if (!function_exists('supabaseAuthAdminUpdateUser')) {
    function supabaseAuthAdminUpdateUser(string $authUserId, array $payload): array
    {
        return supabaseAuthRequest('PUT', 'admin/users/' . rawurlencode($authUserId), $payload, supabaseAuthApiKey());
    }
}

if (!function_exists('supabaseAuthAdminDeleteUser')) {
    function supabaseAuthAdminDeleteUser(string $authUserId): array
    {
        return supabaseAuthRequest('DELETE', 'admin/users/' . rawurlencode($authUserId), null, supabaseAuthApiKey());
    }
}

if (!function_exists('supabaseAuthAdminListUsers')) {
    function supabaseAuthAdminListUsers(int $page = 1, int $perPage = 200): array
    {
        return supabaseAuthRequest('GET', 'admin/users?page=' . $page . '&per_page=' . $perPage, null, supabaseAuthApiKey());
    }
}

if (!function_exists('supabaseAuthAdminFindUserByEmail')) {
    function supabaseAuthAdminFindUserByEmail(string $email): ?array
    {
        $page = 1;

        while ($page <= 10) {
            $result = supabaseAuthAdminListUsers($page, 200);
            $users = is_array($result['data']['users'] ?? null) ? $result['data']['users'] : [];

            foreach ($users as $user) {
                if (strcasecmp((string) ($user['email'] ?? ''), $email) === 0) {
                    return $user;
                }
            }

            if (count($users) < 200) {
                break;
            }

            $page++;
        }

        return null;
    }
}

if (!function_exists('supabaseAuthAdminListUserFactors')) {
    function supabaseAuthAdminListUserFactors(string $authUserId): array
    {
        return supabaseAuthRequest('GET', 'admin/users/' . rawurlencode($authUserId) . '/factors', null, supabaseAuthApiKey());
    }
}
