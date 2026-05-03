<?php
// Fichier qui permet a une entreprise de creer une offre.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // On verifie que l utilisateur a le droit d acceder a cette page.
    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'entreprise') {
        header('Location: /login');
        exit;
    }

    // On refuse les acces qui ne viennent pas du formulaire.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: accueilEntreprise.php');
        exit;
    }

    // On charge les fichiers necessaires.
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../supabaseQuery/restClient.php';

    // On importe les classes utilisees dans ce fichier.
    use Dotenv\Dotenv;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
    $apiKey = $_ENV['SUPABASE_KEY'] ?? '';
    $baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';

    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';

    // On gere le cas ou la valeur attendue est vide.
    if ($startDate === '' || $endDate === '' || strtotime($endDate) < strtotime($startDate)) {
        $_SESSION['result'] = 'Les dates du stage sont invalides.';
        header('Location: accueilEntreprise.php');
        exit;
    }

    $durationDays = (int) floor((strtotime($endDate) - strtotime($startDate)) / 86400);
    $durationWeeks = max(1, (int) ceil(($durationDays + 1) / 7));

    // On recupere et nettoie une valeur envoyee par l utilisateur.
    $missionsInput = trim((string) ($_POST['missions'] ?? ''));
    // On prepare les donnees utilisees dans ce bloc.
    $missions = [];

    // On verifie cette condition.
    if ($missionsInput !== '') {
        $rawLines = preg_split('/\r\n|\r|\n/', $missionsInput) ?: [];

        // On parcourt chaque element de la liste.
        foreach ($rawLines as $line) {
            // On recupere et nettoie une valeur envoyee par l utilisateur.
            $line = trim($line);

            // On gere le cas ou la valeur attendue est vide.
            if ($line === '') {
                continue;
            }

            $line = ltrim($line, "-• \t");
            [$title, $description] = array_pad(explode('|', $line, 2), 2, '');
            // On recupere et nettoie une valeur envoyee par l utilisateur.
            $title = trim($title);
            // On recupere et nettoie une valeur envoyee par l utilisateur.
            $description = trim($description);

            // On verifie cette condition.
            if ($title !== '') {
                $missions[] = [
                    'title' => $title,
                    'description' => $description,
                ];
            }
        }
    }

    // On prepare les donnees utilisees dans ce bloc.
    $newStage = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'company' => $_SESSION['username'] ?? ($_POST['company'] ?? ''),
        'location' => $_POST['location'] ?? '',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'filiere' => $_POST['filiere'] ?? '',
        'company_id' => (int) ($_SESSION['user_id'] ?? 0),
        'duration_weeks' => $durationWeeks,
        'status' => 'ouverte',
    ];

    // On appelle Supabase pour lire ou modifier les donnees.
    $insertResult = supabaseRestRequest(
        'POST',
        "$baseUrl/stages",
        $apiKey,
        $newStage,
        ['Prefer: return=representation']
    );

    $createdStage = is_array($insertResult['data']) && isset($insertResult['data'][0]) ? $insertResult['data'][0] : null;

    // On controle cette condition avant de continuer.
    if (!$insertResult['ok'] || !$createdStage || empty($createdStage['id'])) {
        $_SESSION['error'] = supabaseRestErrorMessage($insertResult, 'Erreur lors de l\'ajout du stage.');
        header('Location: accueilEntreprise.php');
        exit;
    }

    $createdMissions = 0;
    // On prepare les donnees utilisees dans ce bloc.
    $failedMissionTitles = [];

    // On parcourt chaque element de la liste.
    foreach ($missions as $mission) {
        // On prepare les donnees utilisees dans ce bloc.
        $missionPayload = [
            'stage_id' => (int) $createdStage['id'],
            'company_id' => (int) ($_SESSION['user_id'] ?? 0),
            'title' => $mission['title'],
            'description' => $mission['description'],
        ];

        // On appelle Supabase pour lire ou modifier les donnees.
        $missionResult = supabaseRestRequest('POST', "$baseUrl/missions", $apiKey, $missionPayload);

        // On verifie cette condition.
        if ($missionResult['ok']) {
            $createdMissions++;
        } else {
            $failedMissionTitles[] = $mission['title'];
        }
    }

    // On verifie cette condition.
    if (!empty($failedMissionTitles)) {
        $_SESSION['result'] = 'Stage ajouté.';
        $_SESSION['error'] = 'Certaines missions n\'ont pas pu être enregistrées : ' . implode(', ', $failedMissionTitles);
    } elseif ($createdMissions > 0) {
        $_SESSION['result'] = 'Stage ajouté avec succès, avec ' . $createdMissions . ' mission(s).';
    } else {
        $_SESSION['result'] = 'Stage ajouté avec succès.';
    }

    header('Location: accueilEntreprise.php');
    exit;

?>
