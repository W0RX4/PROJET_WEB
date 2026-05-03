<?php
// Fichier qui regroupe les appels REST vers Supabase.

if (!function_exists('supabaseRestRequest')) {
    // Cette fonction envoie une requete REST a Supabase.
    function supabaseRestRequest(string $method, string $endpoint, string $apiKey, ?array $payload = null, array $extraHeaders = []): array
    {
        // On prepare ou lance la requete HTTP.
        $ch = curl_init($endpoint);
        // On prepare les donnees utilisees dans ce bloc.
        $headers = [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ];

        // On verifie cette condition.
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        // On verifie cette condition.
        if (!empty($extraHeaders)) {
            $headers = array_merge($headers, $extraHeaders);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
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
if (!function_exists('supabaseRestErrorMessage')) {
    // Cette fonction extrait un message d erreur lisible.
    function supabaseRestErrorMessage(array $result, string $fallback): string
    {
        // On verifie cette condition.
        if (!empty($result['error'])) {
            return $fallback . ' (' . $result['error'] . ')';
        }

        // On verifie cette condition.
        if (is_array($result['data'])) {
            $details = $result['data']['message']
                ?? $result['data']['details']
                ?? $result['data']['hint']
                ?? null;

            // On verifie cette condition.
            if (is_string($details) && $details !== '') {
                return $fallback . ' (' . $details . ')';
            }
        }

        return $fallback;
    }
}
