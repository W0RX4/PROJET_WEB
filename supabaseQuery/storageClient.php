<?php
// Fichier qui envoie les fichiers dans le stockage Supabase.

if (!function_exists('uploadFileToSupabaseBucket')) {
    // Cette fonction envoie un fichier dans un bucket Supabase.
    function uploadFileToSupabaseBucket(
        array $file,
        string $userEmail,
        string $prefix,
        string $supabaseUrl,
        string $supabaseKey,
        string $bucket = 'candidatures'
    ): ?array {
        // On verifie cette condition.
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $safeEmail = str_replace(['@', '.', '+'], ['_at_', '_', '_'], $userEmail);
        $fileName = $prefix . '_' . time() . '_' . uniqid() . ($extension !== '' ? '.' . $extension : '');
        $filePath = $safeEmail . '/' . $fileName;
        $fileData = file_get_contents((string) $file['tmp_name']);

        // On verifie cette condition.
        if ($fileData === false) {
            return null;
        }

        $url = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . ltrim($filePath, '/');
        $mimeType = mime_content_type((string) $file['tmp_name']) ?: 'application/octet-stream';

        // On prepare ou lance la requete HTTP.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: ' . $mimeType,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // On prepare ou lance la requete HTTP.
        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // On verifie cette condition.
        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return [
            'path' => $filePath,
            'file_name' => (string) ($file['name'] ?? $fileName),
            'bucket' => $bucket,
        ];
    }
}
