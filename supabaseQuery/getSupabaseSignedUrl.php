<?php
// Fichier qui cree des liens temporaires vers les fichiers Supabase.

    function getSupabaseSignedUrl($filePath, $supabaseUrl, $supabaseKey, $expireIn = 900) {
        // On gere le cas ou la valeur attendue est vide.
        if (empty($filePath)) return null;

        $bucket = "candidatures";

        $normalizedPath = ltrim((string)$filePath, '/');

        // On verifie cette condition.
        if (strpos($normalizedPath, $bucket . '/') === 0) {
            $normalizedPath = substr($normalizedPath, strlen($bucket) + 1);
        }

        $decodedPath = rawurldecode($normalizedPath);

        // On prepare les donnees utilisees dans ce bloc.
        $headers = [
            "apikey: " . $supabaseKey,
            "Authorization: Bearer " . $supabaseKey,
            "Content-Type: application/json"
        ];

        $requestSignedUrl = function (string $path, bool $encodeSegments = false) use ($bucket, $headers, $expireIn, $supabaseUrl) {
            // On prepare ou lance la requete HTTP.
            $ch = curl_init();

            $pathForUrl = $path;
            // On controle cette condition avant de continuer.
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

            // On prepare ou lance la requete HTTP.
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // On verifie cette condition.
            if ($httpCode >= 200 && $httpCode < 300) {
                $result = json_decode($response, true);
                // On verifie cette condition.
                if (isset($result['signedURL'])) {
                    return $supabaseUrl . '/storage/v1' . $result['signedURL'];
                }
            }

            return null;
        };

        $signedUrl = $requestSignedUrl($normalizedPath);
        // On controle cette condition avant de continuer.
        if ($signedUrl) {
            return $signedUrl;
        }

        // On verifie cette condition.
        if ($decodedPath !== $normalizedPath) {
            $signedUrl = $requestSignedUrl($decodedPath);
            // On controle cette condition avant de continuer.
            if ($signedUrl) {
                return $signedUrl;
            }
        }

        $signedUrl = $requestSignedUrl($decodedPath, true);
        // On controle cette condition avant de continuer.
        if ($signedUrl) {
            return $signedUrl;
        }

        return null; // Échec
    }

?>