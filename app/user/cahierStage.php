<?php
// Fichier qui gere le cahier de stage de l etudiant.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../includes/trace.php';

// On demarre la session si elle n existe pas encore.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// On verifie que l utilisateur a le droit d acceder a cette page.
if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'etudiant') {
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
$userId = (int) ($_SESSION['user_id'] ?? 0);

// Cette fonction renvoie vers le cahier de stage.
function redirectToCahier(): void
{
    header('Location: cahierStage.php');
    exit;
}

// On appelle Supabase pour lire ou modifier les donnees.
$stagesResult = supabaseRestRequest(
    'GET',
    "$baseUrl/stages?student_id=eq.$userId&select=id,title,company,start_date,end_date,status&order=created_at.desc",
    $apiKey
);
$myStages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];
$activeStage = $myStages[0] ?? null;
$activeStageId = $activeStage ? (int) ($activeStage['id'] ?? 0) : 0;

// On traite les donnees envoyees par le formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    // On controle cette condition avant de continuer.
    if (!$activeStageId) {
        $_SESSION['error'] = "Vous n'avez pas encore de stage actif. Le cahier sera disponible après la confirmation de votre stage.";
        redirectToCahier();
    }

    // On execute l action demandee par le formulaire.
    if ($action === 'add_entry') {
        // On recupere et nettoie une valeur envoyee par l utilisateur.
        $entryDate = trim((string) ($_POST['entry_date'] ?? ''));
        // On recupere et nettoie une valeur envoyee par l utilisateur.
        $content = trim((string) ($_POST['content'] ?? ''));

        // On gere le cas ou la valeur attendue est vide.
        if ($entryDate === '' || $content === '') {
            $_SESSION['error'] = 'La date et le contenu sont obligatoires.';
            redirectToCahier();
        }

        // On appelle Supabase pour lire ou modifier les donnees.
        $insertResult = supabaseRestRequest(
            'POST',
            "$baseUrl/cahier_stage",
            $apiKey,
            [
                'stage_id' => $activeStageId,
                'student_id' => $userId,
                'entry_date' => $entryDate,
                'content' => $content,
            ]
        );

        // On controle cette condition avant de continuer.
        if (!$insertResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($insertResult, "Impossible d'ajouter cette entrée.");
            redirectToCahier();
        }

        stageArchiveLogTrace('cahier_stage_add', "Stage #$activeStageId, date $entryDate");
        $_SESSION['result'] = 'Entrée ajoutée à votre cahier de stage.';
        redirectToCahier();
    }

    // On execute l action demandee par le formulaire.
    if ($action === 'delete_entry') {
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        // On verifie cette condition.
        if ($entryId <= 0) {
            $_SESSION['error'] = 'Entrée invalide.';
            redirectToCahier();
        }

        // On appelle Supabase pour lire ou modifier les donnees.
        $deleteResult = supabaseRestRequest(
            'DELETE',
            "$baseUrl/cahier_stage?id=eq.$entryId&student_id=eq.$userId",
            $apiKey
        );

        // On controle cette condition avant de continuer.
        if (!$deleteResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($deleteResult, 'Suppression impossible.');
            redirectToCahier();
        }

        stageArchiveLogTrace('cahier_stage_delete', "Entrée #$entryId");
        $_SESSION['result'] = 'Entrée supprimée.';
        redirectToCahier();
    }
}

stageArchiveLogPageAccess('/app/user/cahierStage.php');

// On prepare les donnees utilisees dans ce bloc.
$entries = [];
// On controle cette condition avant de continuer.
if ($activeStageId) {
    // On appelle Supabase pour lire ou modifier les donnees.
    $entriesResult = supabaseRestRequest(
        'GET',
        "$baseUrl/cahier_stage?student_id=eq.$userId&stage_id=eq.$activeStageId&select=*&order=entry_date.desc",
        $apiKey
    );
    $entries = is_array($entriesResult['data']) ? $entriesResult['data'] : [];
}

// On charge les fichiers necessaires.
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Cahier de stage</h2>
    <p>Tenez votre tuteur informé : consignez ici vos missions, comptes-rendus quotidiens et apprentissages.</p>
</div>

<?php // On affiche le message de confirmation si besoin. ?>
<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['result']); unset($_SESSION['result']); ?></div>
<?php endif; ?>
<?php // On affiche le message d erreur si besoin. ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php // On controle cette condition avant de continuer. ?>
<?php if (!$activeStage): ?>
    <div class="card">
        <p>Vous n'avez pas encore de stage confirmé. Le cahier sera disponible dès qu'une candidature aura été acceptée et que la convention aura été lancée.</p>
        <a href="mesCandidatures.php" class="btn btn-primary mt-4">Voir mes candidatures</a>
    </div>
<?php else: ?>
    <div class="card">
        <h3><?php echo htmlspecialchars($activeStage['title'] ?? 'Stage en cours'); ?></h3>
        <p style="font-weight: 600; color: var(--text-primary);">
            <?php echo htmlspecialchars($activeStage['company'] ?? ''); ?>
        </p>
        <p style="color: var(--text-secondary);">
            Période : du <?php echo htmlspecialchars($activeStage['start_date'] ?? 'N/A'); ?> au <?php echo htmlspecialchars($activeStage['end_date'] ?? 'N/A'); ?>
        </p>
    </div>

    <div class="card">
        <h3>Nouvelle entrée</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_entry">
            <div class="form-group">
                <label class="form-label" for="entry_date">Date</label>
                <input type="date" id="entry_date" name="entry_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="content">Compte-rendu / missions du jour</label>
                <textarea id="content" name="content" class="form-control" rows="5" placeholder="Décrivez ce que vous avez fait, vos apprentissages, vos difficultés..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Ajouter l'entrée</button>
        </form>
    </div>

    <h3 style="margin-top: 2rem;">Historique (<?php echo count($entries); ?>)</h3>

    <?php // On gere le cas ou la valeur attendue est vide. ?>
    <?php if (empty($entries)): ?>
        <div class="card"><p>Aucune entrée pour le moment.</p></div>
    <?php else: ?>
        <div class="grid-container">
            <?php // On parcourt chaque element de la liste. ?>
            <?php foreach ($entries as $entry): ?>
                <div class="card">
                    <p style="font-weight: 600; color: var(--primary-color); margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($entry['entry_date'] ?? ''); ?>
                    </p>
                    <p style="white-space: pre-line;">
                        <?php echo htmlspecialchars($entry['content'] ?? ''); ?>
                    </p>
                    <form method="POST" onsubmit="return confirm('Supprimer cette entrée ?');" style="margin-top: 1rem;">
                        <input type="hidden" name="action" value="delete_entry">
                        <input type="hidden" name="entry_id" value="<?php echo (int) ($entry['id'] ?? 0); ?>">
                        <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.9rem;">Supprimer</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php // On charge les fichiers necessaires. ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
