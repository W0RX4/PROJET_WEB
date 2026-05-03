<?php
// Fichier qui enregistre une candidature et ses fichiers.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../supabaseQuery/storageClient.php';
require_once __DIR__ . '/../../includes/trace.php';

// On demarre la session si elle n existe pas encore.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// On verifie que l utilisateur a le droit d acceder a cette page.
if (!isset($_SESSION['user_id']) || ($_SESSION['type'] ?? '') !== 'etudiant') {
    header('Location: /login');
    exit;
}

// On refuse les acces qui ne viennent pas du formulaire.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: accueilUser.php');
    exit;
}

// On importe les classes utilisees dans ce fichier.
use Dotenv\Dotenv;

// On verifie cette condition.
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

// On verifie cette condition.
if ($stageId <= 0) {
    $_SESSION['error'] = 'Offre de stage invalide.';
    header('Location: accueilUser.php');
    exit;
}

// On appelle Supabase pour lire ou modifier les donnees.
$stageCheck = supabaseRestRequest(
    'GET',
    "$baseUrl/stages?id=eq.$stageId&select=id,status&limit=1",
    $supabaseKey
);
$stage = is_array($stageCheck['data']) && isset($stageCheck['data'][0]) ? $stageCheck['data'][0] : null;

// On controle cette condition avant de continuer.
if (!$stageCheck['ok'] || !$stage) {
    $_SESSION['error'] = "Cette offre de stage n'existe plus.";
    header('Location: accueilUser.php');
    exit;
}

// On appelle Supabase pour lire ou modifier les donnees.
$existingCheck = supabaseRestRequest(
    'GET',
    "$baseUrl/candidatures?stage_id=eq.$stageId&student_id=eq.$studentId&select=id&limit=1",
    $supabaseKey
);
// On verifie cette condition.
if (is_array($existingCheck['data']) && !empty($existingCheck['data'])) {
    $_SESSION['error'] = "Vous avez déjà postulé à cette offre.";
    header('Location: mesCandidatures.php');
    exit;
}

// On verifie cette condition.
if (!isset($_FILES['CV']) || ($_FILES['CV']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "CV manquant ou invalide.";
    header('Location: postuler.php?stage_id=' . $stageId);
    exit;
}

// On verifie cette condition.
if (!isset($_FILES['cover_letter']) || ($_FILES['cover_letter']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Lettre de motivation manquante ou invalide.";
    header('Location: postuler.php?stage_id=' . $stageId);
    exit;
}

$cvUpload = uploadFileToSupabaseBucket($_FILES['CV'], $studentEmail, 'CV', $supabaseUrl, $supabaseKey);
$letterUpload = uploadFileToSupabaseBucket($_FILES['cover_letter'], $studentEmail, 'LM', $supabaseUrl, $supabaseKey);

// On verifie cette condition.
if ($cvUpload === null || $letterUpload === null) {
    $_SESSION['error'] = "Erreur lors de l'upload des fichiers (format non géré, taille trop grande ou bucket introuvable).";
    header('Location: postuler.php?stage_id=' . $stageId);
    exit;
}

// On prepare les donnees utilisees dans ce bloc.
$payload = [
    'stage_id' => $stageId,
    'student_id' => $studentId,
    'cv_url' => $cvUpload['path'],
    'cover_letter_url' => $letterUpload['path'],
    'status' => 'en attente',
];

// On appelle Supabase pour lire ou modifier les donnees.
$insertResult = supabaseRestRequest(
    'POST',
    "$baseUrl/candidatures",
    $supabaseKey,
    $payload,
    ['Prefer: return=representation']
);

// On controle cette condition avant de continuer.
if (!$insertResult['ok']) {
    $_SESSION['error'] = supabaseRestErrorMessage($insertResult, "Erreur lors de l'enregistrement de votre candidature.");
    header('Location: postuler.php?stage_id=' . $stageId);
    exit;
}

stageArchiveLogTrace('candidature_create', "stage=$stageId");

$_SESSION['result'] = 'Candidature envoyée avec succès.';
header('Location: mesCandidatures.php');
exit;
