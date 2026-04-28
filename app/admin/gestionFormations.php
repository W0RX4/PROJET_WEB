<?php
require_once __DIR__ . '/../../includes/header.php';

if ($_SESSION['type'] !== 'admin') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$apiKey = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';

function redirectGestionFormations(): void
{
    header('Location: gestionFormations.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];

    if ($action === 'create_formation') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        if ($name === '') {
            $_SESSION['error'] = 'Le nom de la formation est obligatoire.';
            redirectGestionFormations();
        }
        $createResult = supabaseRestRequest('POST', "$baseUrl/formations", $apiKey, [
            'name' => $name,
            'department' => $department,
        ]);
        if (!$createResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($createResult, 'Création impossible.');
            redirectGestionFormations();
        }
        $_SESSION['result'] = 'Formation créée.';
        redirectGestionFormations();
    }

    if ($action === 'delete_formation') {
        $formationId = (int) ($_POST['formation_id'] ?? 0);
        if ($formationId <= 0) {
            $_SESSION['error'] = 'Formation invalide.';
            redirectGestionFormations();
        }
        $deleteResult = supabaseRestRequest('DELETE', "$baseUrl/formations?id=eq.$formationId", $apiKey);
        if (!$deleteResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($deleteResult, 'Suppression impossible.');
            redirectGestionFormations();
        }
        $_SESSION['result'] = 'Formation supprimée.';
        redirectGestionFormations();
    }

    if ($action === 'approve_request') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            $_SESSION['error'] = 'Demande invalide.';
            redirectGestionFormations();
        }
        $update = supabaseRestRequest('PATCH', "$baseUrl/formation_requests?id=eq.$requestId", $apiKey, ['status' => 'validée']);
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Validation impossible.');
            redirectGestionFormations();
        }
        $_SESSION['result'] = 'Demande de formation validée.';
        redirectGestionFormations();
    }

    if ($action === 'reject_request') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            $_SESSION['error'] = 'Demande invalide.';
            redirectGestionFormations();
        }
        $update = supabaseRestRequest('PATCH', "$baseUrl/formation_requests?id=eq.$requestId", $apiKey, ['status' => 'refusée']);
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Refus impossible.');
            redirectGestionFormations();
        }
        $_SESSION['result'] = 'Demande de formation refusée.';
        redirectGestionFormations();
    }

    if ($action === 'reuse_student') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        if ($studentId <= 0) {
            $_SESSION['error'] = 'Étudiant invalide.';
            redirectGestionFormations();
        }
        $detachStage = supabaseRestRequest('PATCH', "$baseUrl/users?id=eq.$studentId", $apiKey, ['stage_id' => null]);
        $closeOldStages = supabaseRestRequest('PATCH', "$baseUrl/stages?student_id=eq.$studentId", $apiKey, [
            'student_id' => null,
            'status' => 'archivée',
        ]);

        if (!$detachStage['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($detachStage, 'Réinitialisation impossible.');
            redirectGestionFormations();
        }
        $_SESSION['result'] = 'Étudiant prêt pour une nouvelle année. Profil conservé, stages précédents archivés.';
        redirectGestionFormations();
    }
}

$formationsResult = supabaseRestRequest('GET', "$baseUrl/formations?select=*&order=created_at.desc", $apiKey);
$formations = is_array($formationsResult['data']) ? $formationsResult['data'] : [];

$requestsResult = supabaseRestRequest('GET', "$baseUrl/formation_requests?select=*&order=created_at.desc", $apiKey);
$requests = is_array($requestsResult['data']) ? $requestsResult['data'] : [];

$usersResult = supabaseRestRequest('GET', "$baseUrl/users?type=eq.etudiant&select=id,username,email,stage_id,created_at&order=created_at.asc", $apiKey);
$students = is_array($usersResult['data']) ? $usersResult['data'] : [];

$usersById = [];
foreach ($students as $user) {
    $usersById[(int) ($user['id'] ?? 0)] = $user;
}
?>

<div class="card mes-offres-hero">
    <h2>Formations & profils étudiants</h2>
    <p>Gérez les formations proposées, validez les demandes et préparez la réutilisation des profils étudiants pour la prochaine promotion.</p>
</div>

<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['result']); ?></div>
    <?php unset($_SESSION['result']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="grid-container">
    <div class="card">
        <h3>Ajouter une formation</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_formation">
            <div class="form-group">
                <label class="form-label" for="name">Nom de la formation</label>
                <input class="form-control" type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="department">Département</label>
                <input class="form-control" type="text" id="department" name="department">
            </div>
            <button type="submit" class="btn btn-primary">Créer la formation</button>
        </form>
    </div>

    <div class="card">
        <h3>Formations existantes</h3>
        <?php if (empty($formations)): ?>
            <p>Aucune formation enregistrée.</p>
        <?php else: ?>
            <table style="width:100%; border-collapse:collapse; margin-top:0.5rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border-color); text-align:left;">
                        <th style="padding:0.5rem;">Nom</th>
                        <th style="padding:0.5rem;">Département</th>
                        <th style="padding:0.5rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formations as $formation): ?>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:0.5rem;"><?php echo htmlspecialchars($formation['name'] ?? ''); ?></td>
                            <td style="padding:0.5rem;"><?php echo htmlspecialchars($formation['department'] ?? '—'); ?></td>
                            <td style="padding:0.5rem;">
                                <form method="POST" onsubmit="return confirm('Supprimer cette formation ?');">
                                    <input type="hidden" name="action" value="delete_formation">
                                    <input type="hidden" name="formation_id" value="<?php echo (int) ($formation['id'] ?? 0); ?>">
                                    <button type="submit" class="btn" style="padding:0.25rem 0.6rem; font-size:0.8rem; background:var(--danger-color); color:white;">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3>Demandes de formation des étudiants</h3>
    <?php if (empty($requests)): ?>
        <p>Aucune demande en attente.</p>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse; margin-top:0.5rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border-color); text-align:left;">
                    <th style="padding:0.5rem;">Étudiant</th>
                    <th style="padding:0.5rem;">Formation demandée</th>
                    <th style="padding:0.5rem;">Statut</th>
                    <th style="padding:0.5rem;">Demandée le</th>
                    <th style="padding:0.5rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <?php
                        $student = $usersById[(int) ($request['student_id'] ?? 0)] ?? null;
                        $status = (string) ($request['status'] ?? 'en attente');
                        $badgeClass = $status === 'validée' ? 'badge-valid' : ($status === 'refusée' ? 'badge-progress' : 'badge-pending');
                    ?>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:0.5rem;"><?php echo htmlspecialchars(($student['username'] ?? '—') . ' (' . ($student['email'] ?? '') . ')'); ?></td>
                        <td style="padding:0.5rem;"><?php echo htmlspecialchars($request['formation_name'] ?? '—'); ?></td>
                        <td style="padding:0.5rem;"><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                        <td style="padding:0.5rem;"><?php echo htmlspecialchars(substr((string) ($request['created_at'] ?? ''), 0, 10)); ?></td>
                        <td style="padding:0.5rem; display:flex; gap:0.4rem;">
                            <?php if ($status === 'en attente'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="approve_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int) ($request['id'] ?? 0); ?>">
                                    <button type="submit" class="btn btn-primary" style="padding:0.25rem 0.6rem; font-size:0.8rem;">Valider</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="action" value="reject_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int) ($request['id'] ?? 0); ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding:0.25rem 0.6rem; font-size:0.8rem;">Refuser</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Réutilisation des profils étudiants</h3>
    <p style="color: var(--text-secondary);">Conservez les informations personnelles de l'étudiant et préparez son dossier pour une nouvelle année (les anciens stages sont archivés).</p>
    <?php if (empty($students)): ?>
        <p>Aucun étudiant inscrit.</p>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse; margin-top:0.5rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border-color); text-align:left;">
                    <th style="padding:0.5rem;">Étudiant</th>
                    <th style="padding:0.5rem;">Email</th>
                    <th style="padding:0.5rem;">Stage attribué</th>
                    <th style="padding:0.5rem;">Inscrit depuis</th>
                    <th style="padding:0.5rem;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:0.5rem;"><?php echo htmlspecialchars($student['username'] ?? ''); ?></td>
                        <td style="padding:0.5rem;"><?php echo htmlspecialchars($student['email'] ?? ''); ?></td>
                        <td style="padding:0.5rem;"><?php echo $student['stage_id'] ? '#' . (int) $student['stage_id'] : '—'; ?></td>
                        <td style="padding:0.5rem;"><?php echo htmlspecialchars(substr((string) ($student['created_at'] ?? ''), 0, 10)); ?></td>
                        <td style="padding:0.5rem;">
                            <form method="POST" onsubmit="return confirm('Réinitialiser le dossier de stage de cet étudiant pour la nouvelle année ? Le profil personnel est conservé.');">
                                <input type="hidden" name="action" value="reuse_student">
                                <input type="hidden" name="student_id" value="<?php echo (int) ($student['id'] ?? 0); ?>">
                                <button type="submit" class="btn btn-secondary" style="padding:0.25rem 0.6rem; font-size:0.8rem;">Réutiliser pour l'an prochain</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="accueilAdmin.php">Retour à l'accueil</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
