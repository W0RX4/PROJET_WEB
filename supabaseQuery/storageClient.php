<?php

if (!function_exists('uploadFileToSupabaseBucket')) {
    function uploadFileToSupabaseBucket(
        array $file,
        string $userEmail,
        string $prefix,
        string $supabaseUrl,
        string $supabaseKey,
        string $bucket = 'candidatures'
    ): ?array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $safeEmail = str_replace(['@', '.', '+'], ['_at_', '_', '_'], $userEmail);
        $fileName = $prefix . '_' . time() . '_' . uniqid() . ($extension !== '' ? '.' . $extension : '');
        $filePath = $safeEmail . '/' . $fileName;
        $fileData = file_get_contents((string) $file['tmp_name']);

        if ($fileData === false) {
            return null;
        }

        $url = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . ltrim($filePath, '/');
        $mimeType = mime_content_type((string) $file['tmp_name']) ?: 'application/octet-stream';

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

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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
