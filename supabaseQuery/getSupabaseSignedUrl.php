<?php

    function getSupabaseSignedUrl($filePath, $supabaseUrl, $supabaseKey, $expireIn = 900) {
        if (empty($filePath)) return null;

        $bucket = "candidatures";

        $normalizedPath = ltrim((string)$filePath, '/');

        if (strpos($normalizedPath, $bucket . '/') === 0) {
            $normalizedPath = substr($normalizedPath, strlen($bucket) + 1);
        }

        $decodedPath = rawurldecode($normalizedPath);

        $headers = [
            "apikey: " . $supabaseKey,
            "Authorization: Bearer " . $supabaseKey,
            "Content-Type: application/json"
        ];

        $requestSignedUrl = function (string $path, bool $encodeSegments = false) use ($bucket, $headers, $expireIn, $supabaseUrl) {
            $ch = curl_init();

            $pathForUrl = $path;
            if ($encodeSegments) {
                $segments = explode('/', $path);
                $encodedSegments = array_map('rawurlencode', $segments);
                $pathForUrl = implode('/', $encodedSegments);
            }

            $url = rtrim($supabaseUrl, '/') . '/storage/v1/object/sign/' . $bucket . '/' . $pathForUrl;

            $data = json_encode([
                "expiresIn" => $expireIn
            ]);

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $result = json_decode($response, true);
                if (isset($result['signedURL'])) {
                    return $supabaseUrl . '/storage/v1' . $result['signedURL'];
                }
            }

            return null;
        };

        $signedUrl = $requestSignedUrl($normalizedPath);
        if ($signedUrl) {
            return $signedUrl;
        }

        if ($decodedPath !== $normalizedPath) {
            $signedUrl = $requestSignedUrl($decodedPath);
            if ($signedUrl) {
                return $signedUrl;
            }
        }

        $signedUrl = $requestSignedUrl($decodedPath, true);
        if ($signedUrl) {
            return $signedUrl;
        }

        return null; // Échec
    }

?>