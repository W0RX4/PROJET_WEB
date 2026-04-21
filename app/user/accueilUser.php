<?php
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    // Load env only if not already loaded (useful since we already loaded it in index.php)
    if (!isset($_ENV['SUPABASE_URL'])) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->safeLoad();
    }

    if(!isset($_SESSION['type']) || $_SESSION['type'] !== 'etudiant'){
        header('Location: /login');
        exit;
    }
?>

<div class="card">
    <h2>Bienvenue sur l'espace Étudiant, <?php echo htmlspecialchars($username); ?> !</h2>
    <p>Trouvez le stage qui vous correspond parmi la liste d'offres ci-dessous.</p>
</div>

<h3>Offres de stage disponibles</h3>

<div class="grid-container mt-4">
    <?php
        $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
        $stages = $client->getAllData('stages');
        $allMissions = $client->getAllData('missions') ?: [];
        $missionsByStage = [];

        foreach ($allMissions as $mission) {
            $stageId = (int) ($mission['stage_id'] ?? 0);
            if ($stageId > 0) {
                $missionsByStage[$stageId][] = $mission;
            }
        }
        
        if (empty($stages)) {
            echo "<div class='card'><p>Aucune offre de stage n'est disponible pour le moment.</p></div>";
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
