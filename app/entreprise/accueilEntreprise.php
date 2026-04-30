<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'entreprise') {
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
    $companyId = (int) ($_SESSION['user_id'] ?? 0);
    $companyName = (string) ($_SESSION['username'] ?? '');

    $stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=*", $apiKey);
    $mesStages = [];
    if (is_array($stagesResult['data'])) {
        foreach ($stagesResult['data'] as $stage) {
            $matchesCompanyId = $companyId > 0 && (int) ($stage['company_id'] ?? 0) === $companyId;
            $matchesCompanyName = isset($stage['company']) && (string) $stage['company'] === $companyName;
            if ($matchesCompanyId || $matchesCompanyName) {
                $mesStages[] = $stage;
            }
        }
    }

    $stageIds = array_map(static fn ($stage) => (int) ($stage['id'] ?? 0), $mesStages);
    $stageIds = array_values(array_filter($stageIds));

    $totalCandidatures = 0;
    $pendingConventions = 0;

    if (!empty($stageIds)) {
        $idList = implode(',', $stageIds);
        $candidaturesResult = supabaseRestRequest(
            'GET',
            "$baseUrl/candidatures?stage_id=in.($idList)&select=id",
            $apiKey
        );
        $totalCandidatures = is_array($candidaturesResult['data']) ? count($candidaturesResult['data']) : 0;

        $conventionsResult = supabaseRestRequest(
            'GET',
            "$baseUrl/conventions?stage_id=in.($idList)&company_validated=is.false&select=id",
            $apiKey
        );
        $pendingConventions = is_array($conventionsResult['data']) ? count($conventionsResult['data']) : 0;
    }

    require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Bienvenue sur votre espace Entreprise, <?php echo htmlspecialchars($username); ?> !</h2>
    <p>Gérez vos offres de stage, vos candidats et vos conventions depuis un seul espace.</p>
</div>

<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_SESSION['result']); ?>
    </div>
    <?php unset($_SESSION['result']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($_SESSION['error']); ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="grid-container">
    <div class="card">
        <h3>Offres publiées</h3>
        <p style="font-size: 2rem; font-weight: 700; color: var(--primary-color); margin: 0.5rem 0;"><?php echo count($mesStages); ?></p>
        <a href="mesOffres.php" class="btn btn-secondary mt-4">Voir mes offres</a>
    </div>
    <div class="card">
        <h3>Candidatures reçues</h3>
        <p style="font-size: 2rem; font-weight: 700; color: var(--accent-color); margin: 0.5rem 0;"><?php echo $totalCandidatures; ?></p>
        <a href="mesOffres.php" class="btn btn-secondary mt-4">Consulter les dossiers</a>
    </div>
    <div class="card">
        <h3>Conventions en attente</h3>
        <p style="font-size: 2rem; font-weight: 700; color: var(--success-color); margin: 0.5rem 0;"><?php echo $pendingConventions; ?></p>
        <a href="conventions.php" class="btn btn-secondary mt-4">Valider les conventions</a>
    </div>
</div>

<div class="card">
    <h3>Déposer une nouvelle offre</h3>
    <form action="ajouterStage.php" method="post">
        <div class="form-group">
            <label class="form-label" for="title">Titre du stage</label>
            <input class="form-control" type="text" id="title" name="title" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="filiere">Filière</label>
            <select class="form-control" id="filiere" name="filiere" required>
                <option value="">Sélectionnez une filière</option>
                <option value="informatique">Informatique</option>
                <option value="mathematiques">Mathématiques</option>
                <option value="finance">Finance</option>
                <option value="biologie">Biologie</option>
                <option value="mecanique">Mécanique</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="description">Description du stage</label>
            <textarea class="form-control" id="description" name="description" required></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="missions">Missions proposées</label>
            <textarea
                class="form-control"
                id="missions"
                name="missions"
                placeholder="Une mission par ligne&#10;Ex: Développer le tableau de bord&#10;Ex: Rédiger la documentation | Formaliser les procédures internes"
            ></textarea>
            <small style="display:block; margin-top:0.45rem; color:var(--text-secondary);">
                Optionnel. Une mission par ligne, avec une description après `|` si besoin.
            </small>
        </div>

        <div class="form-group">
            <label class="form-label" for="location">Lieu du stage</label>
            <input class="form-control" type="text" id="location" name="location" required>
        </div>

        <input type="hidden" name="company" value="<?php echo htmlspecialchars($username); ?>">

        <div class="grid-container">
            <div class="form-group">
                <label class="form-label" for="startDate">Date de début</label>
                <input class="form-control" type="date" id="startDate" name="startDate" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="endDate">Date de fin</label>
                <input class="form-control" type="date" id="endDate" name="endDate" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-4">Ajouter l'offre de stage</button>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
