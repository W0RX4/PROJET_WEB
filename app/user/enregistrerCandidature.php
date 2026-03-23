<?php
 ;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../connection/login.php');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Supabase\Client\Functions;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$supabaseKey = $_ENV['SUPABASE_KEY'] ?? '';

// Fonction pour uploader un fichier sur Supabase Storage (Bucket "candidatures")
function uploadToSupabaseStorage($file, $userEmail, $type, $supabaseUrl, $supabaseKey) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $bucket = "candidatures";
    
    // On génère un nom unique pour éviter d'écraser des fichiers
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = $type . "_" . time() . "_" . uniqid() . "." . $extension;
    // On crée un sous-dossier par utilisateur : userEmail/nomFichier
    $filePath = urlencode($userEmail) . "/" . $fileName;

    $fileData = file_get_contents($file['tmp_name']);
    $url = $supabaseUrl . '/storage/v1/object/' . $bucket . '/' . ltrim($filePath, '/');

    // Requête cURL vers l'API Storage
    $ch = curl_init();
    $headers = [
        "apikey: " . $supabaseKey,
        "Authorization: Bearer " . $supabaseKey,
        "Content-Type: " . mime_content_type($file['tmp_name'])
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return $filePath; // Upload réussi, on renvoie le chemin ciblé
    }

    return null; // Échec
}

// 1. Upload des fichiers
$cvPath = null;
if (isset($_FILES['CV'])) {
    $cvPath = uploadToSupabaseStorage($_FILES['CV'], $_SESSION['email'], 'CV', $supabaseUrl, $supabaseKey);
}

$letterPath = null;
if (isset($_FILES['cover_letter'])) {
    $letterPath = uploadToSupabaseStorage($_FILES['cover_letter'], $_SESSION['email'], 'LM', $supabaseUrl, $supabaseKey);
}

if (!$cvPath || !$letterPath) {
    die("Erreur lors de l'upload des fichiers (format non géré, taille trop grande ou bucket introuvable).");
}

// 2. Enregistrement des données en Base de Données
$client = new Functions($supabaseUrl, $supabaseKey);

$newCandidature = [
    'stage_id' => (int)$_POST['stage_id'],
    // Attention: student_id nécessite que l'étudiant se reconnecte pour avoir $_SESSION['user_id']
    'student_id' => $_SESSION['user_id'] ?? 0, 
    'cv_url' => $cvPath, 
    'cover_letter_url' => $letterPath
];

// Vérifie que la table s'appelle bien "candidatures" côté Supabase Database
$insertResult = $client->postData('candidatures', $newCandidature);

$resultData = json_decode($insertResult, true);
if (isset($resultData['code'])) {
    die("Erreur base de données Supabase (" . $resultData['code'] . ") : " . $resultData['message']);
}

// Rediriger vers l'accueil étudiant une fois terminé
header('Location: accueilUser.php');
exit;