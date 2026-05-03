<?php
// Fichier qui regroupe les appels Supabase Auth.

require_once __DIR__ . '/../vendor/autoload.php';

// On importe les classes utilisees dans ce fichier.
use Dotenv\Dotenv;

// On verifie cette condition.
if (!function_exists('supabaseAuthEnsureEnvLoaded')) {
    // On charge la configuration Supabase Auth.
    function supabaseAuthEnsureEnvLoaded(): void
    {
        static $loaded = false;

        // On controle cette condition avant de continuer.
        if ($loaded) {
            return;
        }

        // On verifie cette condition.
        if (!isset($_ENV['SUPABASE_URL']) || !isset($_ENV['SUPABASE_KEY'])) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }

        $loaded = true;
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthBaseUrl')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthBaseUrl(): string
    {
        // On charge la configuration Supabase Auth.
        supabaseAuthEnsureEnvLoaded();
        return rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/auth/v1';
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthApiKey')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthApiKey(): string
    {
        // On charge la configuration Supabase Auth.
        supabaseAuthEnsureEnvLoaded();
        return $_ENV['SUPABASE_KEY'] ?? '';
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthRequest')) {
    // Cette fonction envoie une requete vers Supabase Auth.
    function supabaseAuthRequest(string $method, string $endpoint, ?array $payload = null, ?string $bearerToken = null): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        $url = preg_match('#^https?://#', $endpoint) ? $endpoint : supabaseAuthBaseUrl() . '/' . ltrim($endpoint, '/');
        // On prepare les donnees utilisees dans ce bloc.
        $headers = [
            // On appelle Supabase Auth pour gerer l authentification.
            'apikey: ' . supabaseAuthApiKey(),
            'Accept: application/json',
        ];

        // On verifie cette condition.
        if ($bearerToken !== null && $bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        // On verifie cette condition.
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        // On prepare ou lance la requete HTTP.
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        // On verifie cette condition.
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        // On prepare ou lance la requete HTTP.
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

// On verifie cette condition.
if (!function_exists('supabaseAuthErrorMessage')) {
    // Cette fonction transforme une erreur Auth en message lisible.
    function supabaseAuthErrorMessage(array $result, string $fallback): string
    {
        // On verifie cette condition.
        if (!empty($result['error'])) {
            return $fallback . ' (' . $result['error'] . ')';
        }

        // On verifie cette condition.
        if (is_array($result['data'])) {
            $details = $result['data']['msg']
                ?? $result['data']['message']
                ?? $result['data']['error_description']
                ?? $result['data']['error']
                ?? null;

            // On verifie cette condition.
            if (is_string($details) && $details !== '') {
                return $fallback . ' (' . $details . ')';
            }
        }

        return $fallback;
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthSignInWithPassword')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthSignInWithPassword(string $email, string $password): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
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

// On verifie cette condition.
if (!function_exists('supabaseAuthRefreshSession')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthRefreshSession(string $refreshToken): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest(
            'POST',
            'token?grant_type=refresh_token',
            ['refresh_token' => $refreshToken]
        );
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthGetUser')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthGetUser(string $accessToken): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('GET', 'user', null, $accessToken);
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthLogout')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthLogout(string $accessToken): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('POST', 'logout?scope=local', null, $accessToken);
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthCreateFactor')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthCreateFactor(string $accessToken, array $payload): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('POST', 'factors', $payload, $accessToken);
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthCreateFactorChallenge')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthCreateFactorChallenge(string $accessToken, string $factorId, array $payload = []): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('POST', 'factors/' . rawurlencode($factorId) . '/challenge', $payload === [] ? null : $payload, $accessToken);
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthVerifyFactorChallenge')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthVerifyFactorChallenge(string $accessToken, string $factorId, string $challengeId, string $code): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
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

// On verifie cette condition.
if (!function_exists('supabaseAuthChallengeAndVerifyFactor')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthChallengeAndVerifyFactor(string $accessToken, string $factorId, string $code): array
    {
        // On prepare les donnees utilisees dans ce bloc.
        $headers = [
            // On appelle Supabase Auth pour gerer l authentification.
            'apikey: ' . supabaseAuthApiKey(),
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        // On appelle Supabase Auth pour gerer l authentification.
        $challengeUrl = supabaseAuthBaseUrl() . '/factors/' . rawurlencode($factorId) . '/challenge';
        // On appelle Supabase Auth pour gerer l authentification.
        $verifyUrl = supabaseAuthBaseUrl() . '/factors/' . rawurlencode($factorId) . '/verify';

        // On prepare ou lance la requete HTTP.
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        curl_setopt($ch, CURLOPT_URL, $challengeUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');

        // On prepare ou lance la requete HTTP.
        $challengeRaw = curl_exec($ch);
        $challengeError = curl_error($ch);
        $challengeCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $challengeData = json_decode($challengeRaw ?: '', true);

        $challengeId = (string) ($challengeData['id'] ?? $challengeData['challenge_id'] ?? '');
        // On gere le cas ou la valeur attendue est vide.
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

        // On prepare ou lance la requete HTTP.
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

// On verifie cette condition.
if (!function_exists('supabaseAuthDeleteFactor')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthDeleteFactor(string $accessToken, string $factorId): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('DELETE', 'factors/' . rawurlencode($factorId), null, $accessToken);
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthAdminCreateUser')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthAdminCreateUser(string $email, string $password, array $userMetadata = [], bool $emailConfirm = true): array
    {
        // On prepare les donnees utilisees dans ce bloc.
        $payload = [
            'email' => $email,
            'password' => $password,
            'email_confirm' => $emailConfirm,
        ];

        // On verifie cette condition.
        if ($userMetadata !== []) {
            $payload['user_metadata'] = $userMetadata;
            $payload['app_metadata'] = [
                'type' => $userMetadata['type'] ?? null,
                'username' => $userMetadata['username'] ?? null,
            ];
        }

        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('POST', 'admin/users', $payload, supabaseAuthApiKey());
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthAdminUpdateUser')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthAdminUpdateUser(string $authUserId, array $payload): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('PUT', 'admin/users/' . rawurlencode($authUserId), $payload, supabaseAuthApiKey());
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthAdminDeleteUser')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthAdminDeleteUser(string $authUserId): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('DELETE', 'admin/users/' . rawurlencode($authUserId), null, supabaseAuthApiKey());
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthAdminListUsers')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthAdminListUsers(int $page = 1, int $perPage = 200): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('GET', 'admin/users?page=' . $page . '&per_page=' . $perPage, null, supabaseAuthApiKey());
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthAdminFindUserByEmail')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthAdminFindUserByEmail(string $email): ?array
    {
        $page = 1;

        // On repete le traitement tant que la condition est vraie.
        while ($page <= 10) {
            // On appelle Supabase Auth pour gerer l authentification.
            $result = supabaseAuthAdminListUsers($page, 200);
            $users = is_array($result['data']['users'] ?? null) ? $result['data']['users'] : [];

            // On parcourt chaque element de la liste.
            foreach ($users as $user) {
                // On verifie cette condition.
                if (strcasecmp((string) ($user['email'] ?? ''), $email) === 0) {
                    return $user;
                }
            }

            // On verifie cette condition.
            if (count($users) < 200) {
                break;
            }

            $page++;
        }

        return null;
    }
}

// On verifie cette condition.
if (!function_exists('supabaseAuthAdminListUserFactors')) {
    // Cette fonction regroupe une action reutilisable.
    function supabaseAuthAdminListUserFactors(string $authUserId): array
    {
        // On appelle Supabase Auth pour gerer l authentification.
        return supabaseAuthRequest('GET', 'admin/users/' . rawurlencode($authUserId) . '/factors', null, supabaseAuthApiKey());
    }
}
