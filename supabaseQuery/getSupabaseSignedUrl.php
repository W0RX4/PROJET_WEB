<?php

    function getSupabaseSignedUrl($filePath, $supabaseUrl, $supabaseKey, $expireIn = 900) {
        if (empty($filePath)) return null;

        $bucket = "candidatures";

        // L'API Supabase attend: POST /storage/v1/object/sign/{bucket}
        // avec le chemin du fichier dans le BODY, pas dans l'URL
        $url = $supabaseUrl . '/storage/v1/object/sign/' . $bucket;

        $ch = curl_init();
        $headers = [
            "apikey: " . $supabaseKey,
            "Authorization: Bearer " . $supabaseKey,
            "Content-Type: application/json"
        ];

        $data = json_encode([
            "expiresIn" => $expireIn,
            "path" => $filePath
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
            if(isset($result['signedURL'])) {
                return $supabaseUrl . '/storage/v1' . $result['signedURL']; 
            }
        }

        return null; // Échec
    }

?>