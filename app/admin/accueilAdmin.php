<?php
    require_once '../../includes/header.php';
    if($_SESSION['type'] !== 'admin'){
        header('Location: ../../connection/login.php');
        exit;
    }

    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../supabaseQuery/restClient.php';

    use Dotenv\Dotenv;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();

    $apiKey = $_ENV['SUPABASE_KEY'] ?? '';
    $baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';

    $usersResult = supabaseRestRequest('GET', "$baseUrl/users?select=id,type", $apiKey);
    $users = is_array($usersResult['data']) ? $usersResult['data'] : [];
    $usersCount = [
        'etudiant' => 0,
        'entreprise' => 0,
        'tuteur' => 0,
        'jury' => 0,
        'admin' => 0,
    ];
    foreach ($users as $u) {
        $type = (string) ($u['type'] ?? '');
        if (isset($usersCount[$type])) {
            $usersCount[$type]++;
        }
    }

    $stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=id,status", $apiKey);
    $stages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];
    $totalStages = count($stages);
    $archivedStages = 0;
    foreach ($stages as $s) {
        if (($s['status'] ?? '') === 'archivée') {
            $archivedStages++;
        }
    }

    $conventionsResult = supabaseRestRequest('GET', "$baseUrl/conventions?admin_validated=is.false&select=id", $apiKey);
    $pendingConventions = is_array($conventionsResult['data']) ? count($conventionsResult['data']) : 0;
?>

<div class="card mes-offres-hero">
    <h2>Tableau de bord Administrateur</h2>
    <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>. Pilotez la plateforme depuis cet espace.</p>
</div>

<div class="grid-container">
    <div class="card">
        <h3>Comptes</h3>
        <p style="color: var(--text-secondary);">
            <?php echo $usersCount['etudiant']; ?> étudiant(s), <?php echo $usersCount['entreprise']; ?> entreprise(s), <?php echo $usersCount['tuteur']; ?> tuteur(s), <?php echo $usersCount['jury']; ?> jury, <?php echo $usersCount['admin']; ?> admin.
        </p>
        <a href="/app/admin/gestionComptes.php" class="btn btn-primary mt-4">Gérer les comptes</a>
    </div>

    <div class="card">
        <h3>Offres de stage</h3>
        <p style="color: var(--text-secondary);">
            <?php echo $totalStages; ?> offre(s) au total, dont <?php echo $archivedStages; ?> archivée(s).
        </p>
        <a href="/app/admin/gestionOffres.php" class="btn btn-primary mt-4">Diffuser & filtrer</a>
    </div>

    <div class="card">
        <h3>Conventions à valider</h3>
        <p style="color: var(--text-secondary);">
            <?php echo $pendingConventions; ?> convention(s) en attente de validation administrative.
        </p>
        <a href="/app/admin/validerConventions.php" class="btn btn-primary mt-4">Valider les conventions</a>
    </div>

    <div class="card">
        <h3>Archives</h3>
        <p style="color: var(--text-secondary);">
            Centralisez les dossiers de stage terminés et archivez-les en un clic.
        </p>
        <a href="/app/admin/archives.php" class="btn btn-primary mt-4">Voir les archives</a>
    </div>

    <div class="card">
        <h3>Formations & promotions</h3>
        <p style="color: var(--text-secondary);">
            Gérez les formations et préparez la réutilisation des profils étudiants pour la rentrée suivante.
        </p>
        <a href="/app/admin/gestionFormations.php" class="btn btn-primary mt-4">Gérer les formations</a>
    </div>

    <div class="card">
        <h3>Suivi global des stages</h3>
        <p style="color: var(--text-secondary);">
            Vue centralisée des offres, candidatures et conventions sur la plateforme.
        </p>
        <a href="/app/admin/gestionOffres.php" class="btn btn-secondary mt-4">Suivi des stages</a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
