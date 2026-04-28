<?php
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../supabaseQuery/restClient.php';
    require_once __DIR__ . '/../../includes/trace.php';

    use Dotenv\Dotenv;

    if (!isset($_ENV['SUPABASE_URL'])) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->safeLoad();
    }

    if(!isset($_SESSION['type']) || $_SESSION['type'] !== 'etudiant'){
        header('Location: /login');
        exit;
    }

    stageArchiveLogPageAccess('/app/user/accueilUser.php');

    $apiKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
    $baseUrl = rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/') . '/rest/v1';

    $filterFiliere = trim((string) ($_GET['filiere'] ?? ''));
    $filterLocation = trim((string) ($_GET['location'] ?? ''));
    $filterMinWeeks = (int) ($_GET['min_weeks'] ?? 0);
    $filterKeyword = trim((string) ($_GET['q'] ?? ''));

    $queryParts = ['select=*', 'order=created_at.desc'];

    if ($filterFiliere !== '') {
        $queryParts[] = 'filiere=ilike.' . rawurlencode('%' . $filterFiliere . '%');
    }
    if ($filterLocation !== '') {
        $queryParts[] = 'location=ilike.' . rawurlencode('%' . $filterLocation . '%');
    }
    if ($filterMinWeeks > 0) {
        $queryParts[] = 'duration_weeks=gte.' . $filterMinWeeks;
    }
    if ($filterKeyword !== '') {
        $queryParts[] = 'title=ilike.' . rawurlencode('%' . $filterKeyword . '%');
    }

    $stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?" . implode('&', $queryParts), $apiKey);
    $stages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];

    $missionsResult = supabaseRestRequest('GET', "$baseUrl/missions?select=*", $apiKey);
    $allMissions = is_array($missionsResult['data']) ? $missionsResult['data'] : [];
    $missionsByStage = [];
    foreach ($allMissions as $mission) {
        $stageId = (int) ($mission['stage_id'] ?? 0);
        if ($stageId > 0) {
            $missionsByStage[$stageId][] = $mission;
        }
    }

    if ($filterFiliere !== '' || $filterLocation !== '' || $filterMinWeeks > 0 || $filterKeyword !== '') {
        stageArchiveLogTrace('stages_search', "filiere=$filterFiliere, location=$filterLocation, weeks>=$filterMinWeeks, q=$filterKeyword");
    }
?>

<div class="card mes-offres-hero">
    <h2>Bienvenue sur l'espace Étudiant, <?php echo htmlspecialchars($username); ?> !</h2>
    <p>Trouvez le stage qui vous correspond parmi la liste d'offres ci-dessous.</p>
</div>

<div class="card">
    <h3>Rechercher une offre</h3>
    <form method="GET">
        <div class="grid-container" style="margin-bottom: 1rem;">
            <div class="form-group">
                <label class="form-label" for="q">Mot-clé (titre)</label>
                <input type="text" id="q" name="q" class="form-control" value="<?php echo htmlspecialchars($filterKeyword); ?>" placeholder="Ex : développeur web">
            </div>
            <div class="form-group">
                <label class="form-label" for="filiere">Filière</label>
                <input type="text" id="filiere" name="filiere" class="form-control" value="<?php echo htmlspecialchars($filterFiliere); ?>" placeholder="Ex : informatique">
            </div>
            <div class="form-group">
                <label class="form-label" for="location">Lieu</label>
                <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($filterLocation); ?>" placeholder="Ex : Paris">
            </div>
            <div class="form-group">
                <label class="form-label" for="min_weeks">Durée minimale (semaines)</label>
                <input type="number" id="min_weeks" name="min_weeks" class="form-control" min="0" value="<?php echo $filterMinWeeks > 0 ? (int) $filterMinWeeks : ''; ?>" placeholder="Ex : 8">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Filtrer les offres</button>
        <a href="/app/user/accueilUser.php" class="btn btn-secondary">Réinitialiser</a>
    </form>
</div>

<h3 style="margin-top: 2rem;">Offres de stage disponibles (<?php echo count($stages); ?>)</h3>

<div class="grid-container mt-4">
    <?php
        if (empty($stages)) {
            echo "<div class='card'><p>Aucune offre de stage ne correspond à votre recherche.</p></div>";
        } else {
            foreach ($stages as $stage) {
                $stageId = (int) ($stage['id'] ?? 0);
                $stageMissions = $missionsByStage[$stageId] ?? [];
                ?>
                <div class="card">
                    <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($stage['title'] ?? 'Titre non disponible'); ?>
                    </h3>
                    <p style="font-weight: 500; color: var(--text-primary); margin-bottom: 1rem;">
                        <?php echo htmlspecialchars($stage['company'] ?? 'Entreprise non disponible'); ?>
                    </p>
                    <p style="font-size: 0.875rem; margin-bottom: 1rem;">
                        <?php echo nl2br(htmlspecialchars($stage['description'] ?? 'Description non disponible')); ?>
                    </p>
                    <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
                        <p><?php echo htmlspecialchars($stage['location'] ?? 'Lieu non disponible'); ?></p>
                        <p>Du <?php echo htmlspecialchars($stage['start_date'] ?? 'N/A'); ?> au <?php echo htmlspecialchars($stage['end_date'] ?? 'N/A'); ?></p>
                        <?php if (!empty($stage['filiere'])): ?>
                            <p><strong>Filière :</strong> <?php echo htmlspecialchars($stage['filiere']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($stage['duration_weeks'])): ?>
                            <p><strong>Durée :</strong> <?php echo (int) $stage['duration_weeks']; ?> semaines</p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($stageMissions)): ?>
                        <div style="margin-bottom: 1.25rem;">
                            <p style="font-weight: 600; margin-bottom: 0.5rem;">Missions proposées</p>
                            <ul style="padding-left: 1.2rem; color: var(--text-secondary);">
                                <?php foreach ($stageMissions as $mission): ?>
                                    <li style="margin-bottom: 0.4rem;">
                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($mission['title'] ?? 'Mission'); ?></strong>
                                        <?php if (!empty($mission['description'])): ?>
                                            : <?php echo htmlspecialchars($mission['description']); ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <a href="postuler.php?stage_id=<?php echo urlencode($stage['id'] ?? ''); ?>" class="btn btn-primary mt-4">Postuler</a>
                </div>
                <?php
            }
        }
    ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
