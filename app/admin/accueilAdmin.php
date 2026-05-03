<?php
// Fichier qui affiche le tableau de bord administrateur.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // On verifie que l utilisateur a le droit d acceder a cette page.
    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'admin') {
        header('Location: /login');
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

    // On appelle Supabase pour lire ou modifier les donnees.
    $usersResult = supabaseRestRequest('GET', "$baseUrl/users?select=id,type", $apiKey);
    $users = is_array($usersResult['data']) ? $usersResult['data'] : [];
    // On prepare les donnees utilisees dans ce bloc.
    $usersCount = [
        'etudiant' => 0,
        'entreprise' => 0,
        'tuteur' => 0,
        'jury' => 0,
        'admin' => 0,
    ];
    // On parcourt chaque element de la liste.
    foreach ($users as $u) {
        $type = (string) ($u['type'] ?? '');
        // On verifie cette condition.
        if (isset($usersCount[$type])) {
            $usersCount[$type]++;
        }
    }

    // On appelle Supabase pour lire ou modifier les donnees.
    $stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=id,status,end_date,student_id", $apiKey);
    $stages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];
    $totalStages = count($stages);
    $archivedStages = 0;
    $finishedStages = 0;
    $today = date('Y-m-d');
    // On parcourt chaque element de la liste.
    foreach ($stages as $s) {
        $status = (string) ($s['status'] ?? '');
        $endDate = (string) ($s['end_date'] ?? '');
        $hasStudent = (int) ($s['student_id'] ?? 0) > 0;

        // On verifie cette condition.
        if ($status === 'archivée') {
            $archivedStages++;
        }
        // On verifie cette condition.
        if ($status !== 'archivée' && $hasStudent && (($endDate !== '' && $endDate < $today) || $status === 'fermée')) {
            $finishedStages++;
        }
    }

    // On appelle Supabase pour lire ou modifier les donnees.
    $conventionsResult = supabaseRestRequest('GET', "$baseUrl/conventions?admin_validated=is.false&select=id", $apiKey);
    $pendingConventions = is_array($conventionsResult['data']) ? count($conventionsResult['data']) : 0;

    // On appelle Supabase pour lire ou modifier les donnees.
    $validatedConventionsResult = supabaseRestRequest('GET', "$baseUrl/conventions?company_validated=is.true&tutor_validated=is.true&admin_validated=is.true&select=id", $apiKey);
    $validatedConventions = is_array($validatedConventionsResult['data']) ? count($validatedConventionsResult['data']) : 0;

    // On appelle Supabase pour lire ou modifier les donnees.
    $pendingApplicationsResult = supabaseRestRequest('GET', "$baseUrl/candidatures?status=eq." . rawurlencode('en attente') . "&select=id", $apiKey);
    $pendingApplications = is_array($pendingApplicationsResult['data']) ? count($pendingApplicationsResult['data']) : 0;

    // On charge les fichiers necessaires.
    require_once __DIR__ . '/../../includes/header.php';
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
            <?php echo $totalStages; ?> offre(s) au total, dont <?php echo $archivedStages; ?> archivée(s) et <?php echo $finishedStages; ?> terminée(s) à archiver.
        </p>
        <a href="/app/admin/gestionOffres.php" class="btn btn-primary mt-4">Diffuser & filtrer</a>
    </div>

    <div class="card">
        <h3>Conventions à valider</h3>
        <p style="color: var(--text-secondary);">
            <?php echo $pendingConventions; ?> convention(s) en attente de validation administrative, <?php echo $validatedConventions; ?> entièrement validée(s).
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
            <?php echo $pendingApplications; ?> candidature(s) en attente, avec une vue centralisée des offres, candidatures et conventions.
        </p>
        <a href="/app/admin/gestionOffres.php" class="btn btn-secondary mt-4">Suivi des stages</a>
    </div>
</div>

<?php // On charge les fichiers necessaires. ?>
<?php require_once '../../includes/footer.php'; ?>
