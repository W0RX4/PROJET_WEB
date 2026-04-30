<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../supabaseQuery/storageClient.php';
require_once __DIR__ . '/../../includes/trace.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['type'] ?? '') !== 'etudiant') {
    header('Location: /login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: accueilUser.php');
    exit;
}

use Dotenv\Dotenv;

if (!isset($_ENV['SUPABASE_URL'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
}

$supabaseUrl = (string) ($_ENV['SUPABASE_URL'] ?? '');
$supabaseKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
$baseUrl = rtrim($supabaseUrl, '/') . '/rest/v1';

$studentId = (int) ($_SESSION['user_id'] ?? 0);
$studentEmail = (string) ($_SESSION['email'] ?? '');
$stageId = (int) ($_POST['stage_id'] ?? 0);

if ($stageId <= 0) {
    $_SESSION['error'] = 'Offre de stage invalide.';
    header('Location: accueilUser.php');
    exit;
}

$stageCheck = supabaseRestRequest(
    'GET',
    "$baseUrl/stages?id=eq.$stageId&select=id,status&limit=1",
    $supabaseKey
);
$stage = is_array($stageCheck['data']) && isset($stageCheck['data'][0]) ? $stageCheck['data'][0] : null;

if (!$stageCheck['ok'] || !$stage) {
    $_SESSION['error'] = "Cette offre de stage n'existe plus.";
    header('Location: accueilUser.php');
    exit;
}

$existingCheck = supabaseRestRequest(
    'GET',
    "$baseUrl/candidatures?stage_id=eq.$stageId&student_id=eq.$studentId&select=id&limit=1",
    $supabaseKey
);
if (is_array($existingCheck['data']) && !empty($existingCheck['data'])) {
    $_SESSION['error'] = "Vous avez déjà postulé à cette offre.";
    header('Location: mesCandidatures.php');
    exit;
}

if (!isset($_FILES['CV']) || ($_FILES['CV']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "CV manquant ou invalide.";
    header('Location: postuler.php?stage_id=' . $stageId);
    exit;
}

if (!isset($_FILES['cover_letter']) || ($_FILES['cover_letter']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Lettre de motivation manquante ou invalide.";
    header('Location: postuler.php?stage_id=' . $stageId);
    exit;
}

$cvUpload = uploadFileToSupabaseBucket($_FILES['CV'], $studentEmail, 'CV', $supabaseUrl, $supabaseKey);
$letterUpload = uploadFileToSupabaseBucket($_FILES['cover_letter'], $studentEmail, 'LM', $supabaseUrl, $supabaseKey);

if ($cvUpload === null || $letterUpload === null) {
    $_SESSION['error'] = "Erreur lors de l'upload des fichiers (format non géré, taille trop grande ou bucket introuvable).";
    header('Location: postuler.php?stage_id=' . $stageId);
    exit;
}

$payload = [
    'stage_id' => $stageId,
    'student_id' => $studentId,
    'cv_url' => $cvUpload['path'],
    'cover_letter_url' => $letterUpload['path'],
    'status' => 'en attente',
];

$insertResult = supabaseRestRequest(
    'POST',
    "$baseUrl/candidatures",
    $supabaseKey,
    $payload,
    ['Prefer: return=representation']
);

if (!$insertResult['ok']) {
    $_SESSION['error'] = supabaseRestErrorMessage($insertResult, "Erreur lors de l'enregistrement de votre candidature.");
    header('Location: postuler.php?stage_id=' . $stageId);
    exit;
}

stageArchiveLogTrace('candidature_create', "stage=$stageId");

$_SESSION['result'] = 'Candidature envoyée avec succès.';
header('Location: mesCandidatures.php');
exit;
