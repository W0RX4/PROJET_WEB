<?php
// Fichier qui affiche l annuaire des membres avec filtres.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../includes/trace.php';

// On demarre la session si elle n existe pas encore.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// On verifie que l utilisateur a le droit d acceder a cette page.
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// On importe les classes utilisees dans ce fichier.
use Dotenv\Dotenv;

// On verifie cette condition.
if (!isset($_ENV['SUPABASE_URL'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
}

$apiKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
$baseUrl = rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/') . '/rest/v1';

stageArchiveLogPageAccess('/app/account/annuaire.php');

// On recupere et nettoie une valeur envoyee par l utilisateur.
$searchName = trim((string) ($_GET['q'] ?? ''));
// On recupere et nettoie une valeur envoyee par l utilisateur.
$filterType = trim((string) ($_GET['type'] ?? ''));
// On recupere et nettoie une valeur envoyee par l utilisateur.
$filterDate = trim((string) ($_GET['since'] ?? ''));

// On prepare les donnees utilisees dans ce bloc.
$allowedTypes = ['admin', 'etudiant', 'entreprise', 'tuteur', 'jury'];

// On prepare les donnees utilisees dans ce bloc.
$queryParts = ['select=id,username,email,type,created_at', 'order=username.asc'];

// On verifie cette condition.
if ($searchName !== '') {
    $queryParts[] = 'username=ilike.' . rawurlencode('%' . $searchName . '%');
}

// On verifie cette condition.
if (in_array($filterType, $allowedTypes, true)) {
    $queryParts[] = 'type=eq.' . rawurlencode($filterType);
}

// On verifie cette condition.
if ($filterDate !== '') {
    $queryParts[] = 'created_at=gte.' . rawurlencode($filterDate);
}

// On appelle Supabase pour lire ou modifier les donnees.
$usersResult = supabaseRestRequest(
    'GET',
    "$baseUrl/users?" . implode('&', $queryParts),
    $apiKey
);
$members = is_array($usersResult['data']) ? $usersResult['data'] : [];

stageArchiveLogTrace('annuaire_search', "q=$searchName, type=$filterType, since=$filterDate");

// On charge les fichiers necessaires.
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Annuaire des membres</h2>
    <p>Consultez les profils des autres membres de la plateforme. Utilisez les filtres ci-dessous pour affiner votre recherche.</p>
</div>

<div class="card">
    <h3>Filtres de recherche</h3>
    <form method="GET">
        <div class="grid-container" style="margin-bottom: 1rem;">
            <div class="form-group">
                <label class="form-label" for="q">Nom d'utilisateur</label>
                <input type="text" id="q" name="q" class="form-control" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="Rechercher par nom...">
            </div>
            <div class="form-group">
                <label class="form-label" for="type">Type de profil</label>
                <select id="type" name="type" class="form-control">
                    <option value="">Tous les types</option>
                    <?php // On parcourt chaque element de la liste. ?>
                    <?php foreach ($allowedTypes as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $filterType === $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="since">Inscrits depuis</label>
                <input type="date" id="since" name="since" class="form-control" value="<?php echo htmlspecialchars($filterDate); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Filtrer</button>
        <a href="/app/account/annuaire.php" class="btn btn-secondary">Réinitialiser</a>
    </form>
</div>

<h3 style="margin-top: 2rem;">Résultats (<?php echo count($members); ?>)</h3>

<?php // On gere le cas ou la valeur attendue est vide. ?>
<?php if (empty($members)): ?>
    <div class="card"><p>Aucun membre ne correspond à ces critères.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php // On parcourt chaque element de la liste. ?>
        <?php foreach ($members as $member): ?>
            <div class="card">
                <h3 style="color: var(--primary-color);"><?php echo htmlspecialchars($member['username'] ?? ''); ?></h3>
                <p>
                    <span class="badge badge-pending"><?php echo htmlspecialchars(ucfirst((string) ($member['type'] ?? ''))); ?></span>
                </p>
                <p style="color: var(--text-secondary); font-size: 0.875rem;">
                    <strong>Email :</strong> <?php echo htmlspecialchars($member['email'] ?? ''); ?><br>
                    <strong>Inscrit le :</strong> <?php echo htmlspecialchars((string) ($member['created_at'] ?? 'N/A')); ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php // On charge les fichiers necessaires. ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
