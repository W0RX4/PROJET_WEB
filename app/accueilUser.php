<?php
    require_once '../includes/header.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    if($_SESSION['type'] !== 'etudiant'){
        header('Location: ../connection/login.php');
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
        
        if (empty($stages)) {
            echo "<div class='card'><p>Aucune offre de stage n'est disponible pour le moment.</p></div>";
        } else {
            foreach ($stages as $stage) {
                ?>
                <div class="card">
                    <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($stage['title'] ?? 'Titre non disponible'); ?>
                    </h3>
                    <p style="font-weight: 500; color: var(--text-primary); margin-bottom: 1rem;">
                        🏢 <?php echo htmlspecialchars($stage['company'] ?? 'Entreprise non disponible'); ?>
                    </p>
                    <p style="font-size: 0.875rem; margin-bottom: 1rem;">
                        <?php echo nl2br(htmlspecialchars($stage['description'] ?? 'Description non disponible')); ?>
                    </p>
                    <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
                        <p>📍 <?php echo htmlspecialchars($stage['location'] ?? 'Lieu non disponible'); ?></p>
                        <p>🗓️ Du <?php echo htmlspecialchars($stage['start_date'] ?? 'N/A'); ?> au <?php echo htmlspecialchars($stage['end_date'] ?? 'N/A'); ?></p>
                    </div>
                    <button class="btn btn-primary btn-block">Postuler</button>
                </div>
                <?php
            }
        }
    ?>
</div>

<?php require_once '../includes/footer.php'; ?>