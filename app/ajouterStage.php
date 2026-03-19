<?php 
    session_start();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: accueilEntreprise.php');
        exit;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');

    $newStage = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'company' => $_POST['company'] ?? '',
        'location' => $_POST['location'] ?? '',
        'start_date' => $_POST['startDate'] ?? '',
        'end_date' => $_POST['endDate'] ?? '',
    ];

    $insertResult = $client->postData('stages', $newStage);

    if (is_string($insertResult) && str_contains($insertResult, '"code"')) {
        $_SESSION['result'] = 'Erreur lors de l\'ajout du stage.';
    } else {
        $_SESSION['result'] = 'Stage ajouté avec succès.';
    }

    header('Location: accueilEntreprise.php');
    exit;

?>