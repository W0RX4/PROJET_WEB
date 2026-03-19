<?php
    session_start();
    require_once __DIR__ . '/../vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    if($_SESSION['type'] !== 'etudiant'){
        header('Location: ../connection/login.php');
        exit;
    }
    

    echo "Bienvenue sur la page d'accueil " . $username . " !";

    ?>
    <br>
    <br>

    <?php


    echo "Liste des stages disponibles :";
    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
    $stages = $client->getAllData('stages');
    foreach ($stages as $stage) {
        echo "<div>";
        echo "<h3>" . ($stage['title'] ?? 'Titre non disponible') . "</h3>";
        echo "<p>" . ($stage['description'] ?? 'Description non disponible') . "</p>";
        echo "<p>Entreprise : " . ($stage['company'] ?? 'Entreprise non disponible') . "</p>";
        echo "<p>Lieu : " . ($stage['location'] ?? 'Lieu non disponible') . "</p>";
        echo "<p>Date de début : " . ($stage['start_date'] ?? 'Date de début non disponible') . "</p>";
        echo "<p>Date de fin : " . ($stage['end_date'] ?? 'Date de fin non disponible') . "</p>";
        echo "<button>Postuler</button>";
        echo "</div>";
    }


    
?>