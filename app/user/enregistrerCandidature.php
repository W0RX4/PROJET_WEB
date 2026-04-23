<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../connection/login.php');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/storageClient.php';

use Dotenv\Dotenv;
use Supabase\Client\Functions;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$supabaseKey = $_ENV['SUPABASE_KEY'] ?? '';

// 1. Upload des fichiers
$cvPath = null;
if (isset($_FILES['CV'])) {
    $cvUpload = uploadFileToSupabaseBucket($_FILES['CV'], $_SESSION['email'], 'CV', $supabaseUrl, $supabaseKey);
    $cvPath = $cvUpload['path'] ?? null;
}

$letterPath = null;
if (isset($_FILES['cover_letter'])) {
    $letterUpload = uploadFileToSupabaseBucket($_FILES['cover_letter'], $_SESSION['email'], 'LM', $supabaseUrl, $supabaseKey);
    $letterPath = $letterUpload['path'] ?? null;
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
