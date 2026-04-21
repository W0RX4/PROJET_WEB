<?php

if (!function_exists('supabaseRestRequest')) {
    function supabaseRestRequest(string $method, string $endpoint, string $apiKey, ?array $payload = null, array $extraHeaders = []): array
    {
        $ch = curl_init($endpoint);
        $headers = [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ];

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        if (!empty($extraHeaders)) {
            $headers = array_merge($headers, $extraHeaders);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
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

if (!function_exists('supabaseRestErrorMessage')) {
    function supabaseRestErrorMessage(array $result, string $fallback): string
    {
        if (!empty($result['error'])) {
            return $fallback . ' (' . $result['error'] . ')';
        }

        if (is_array($result['data'])) {
            $details = $result['data']['message']
                ?? $result['data']['details']
                ?? $result['data']['hint']
                ?? null;

            if (is_string($details) && $details !== '') {
                return $fallback . ' (' . $details . ')';
            }
        }

        return $fallback;
    }
}
