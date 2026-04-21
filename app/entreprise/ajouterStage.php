<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'entreprise') {
        header('Location: /login');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: accueilEntreprise.php');
        exit;
    }

    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../supabaseQuery/restClient.php';

    use Dotenv\Dotenv;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
    $apiKey = $_ENV['SUPABASE_KEY'] ?? '';
    $baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';

    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';

    if ($startDate === '' || $endDate === '' || strtotime($endDate) < strtotime($startDate)) {
        $_SESSION['result'] = 'Les dates du stage sont invalides.';
        header('Location: accueilEntreprise.php');
        exit;
    }

    $durationDays = (int) floor((strtotime($endDate) - strtotime($startDate)) / 86400);
    $durationWeeks = max(1, (int) ceil(($durationDays + 1) / 7));

    $missionsInput = trim((string) ($_POST['missions'] ?? ''));
    $missions = [];

    if ($missionsInput !== '') {
        $rawLines = preg_split('/\r\n|\r|\n/', $missionsInput) ?: [];

        foreach ($rawLines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $line = ltrim($line, "-• \t");
            [$title, $description] = array_pad(explode('|', $line, 2), 2, '');
            $title = trim($title);
            $description = trim($description);

            if ($title !== '') {
                $missions[] = [
                    'title' => $title,
                    'description' => $description,
                ];
            }
        }
    }

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

    $insertResult = supabaseRestRequest(
        'POST',
        "$baseUrl/stages",
        $apiKey,
        $newStage,
        ['Prefer: return=representation']
    );

    $createdStage = is_array($insertResult['data']) && isset($insertResult['data'][0]) ? $insertResult['data'][0] : null;

    if (!$insertResult['ok'] || !$createdStage || empty($createdStage['id'])) {
        $_SESSION['error'] = supabaseRestErrorMessage($insertResult, 'Erreur lors de l\'ajout du stage.');
        header('Location: accueilEntreprise.php');
        exit;
    }

    $createdMissions = 0;
    $failedMissionTitles = [];

    foreach ($missions as $mission) {
        $missionPayload = [
            'stage_id' => (int) $createdStage['id'],
            'company_id' => (int) ($_SESSION['user_id'] ?? 0),
            'title' => $mission['title'],
            'description' => $mission['description'],
        ];

        $missionResult = supabaseRestRequest('POST', "$baseUrl/missions", $apiKey, $missionPayload);

        if ($missionResult['ok']) {
            $createdMissions++;
        } else {
            $failedMissionTitles[] = $mission['title'];
        }
    }

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
