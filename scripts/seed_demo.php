<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../supabaseQuery/addUserSupabase.php';
require_once __DIR__ . '/../supabaseQuery/restClient.php';
require_once __DIR__ . '/../supabaseQuery/userProfileClient.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$supabaseUrl = rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/');
$apiKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
$baseUrl = $supabaseUrl . '/rest/v1';

if ($supabaseUrl === '' || $apiKey === '') {
    fwrite(STDERR, "Variables SUPABASE_URL et SUPABASE_KEY requises dans .env\n");
    exit(1);
}

$accounts = [
    [
        'email' => 'admin.demo@portfolium.fr',
        'username' => 'Admin Demo',
        'password' => 'DemoStage2026!',
        'type' => 'admin',
    ],
    [
        'email' => 'entreprise.demo@portfolium.fr',
        'username' => 'TechNova',
        'password' => 'DemoStage2026!',
        'type' => 'entreprise',
    ],
    [
        'email' => 'etudiant.demo@portfolium.fr',
        'username' => 'Alice Martin',
        'password' => 'DemoStage2026!',
        'type' => 'etudiant',
    ],
    [
        'email' => 'etudiant2.demo@portfolium.fr',
        'username' => 'Lucas Bernard',
        'password' => 'DemoStage2026!',
        'type' => 'etudiant',
    ],
    [
        'email' => 'tuteur.demo@portfolium.fr',
        'username' => 'Tuteur Demo',
        'password' => 'DemoStage2026!',
        'type' => 'tuteur',
    ],
    [
        'email' => 'jury.demo@portfolium.fr',
        'username' => 'Jury Demo',
        'password' => 'DemoStage2026!',
        'type' => 'jury',
    ],
];

function seedInfo(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function seedFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function firstRow(array $result): ?array
{
    return is_array($result['data'] ?? null) && isset($result['data'][0]) && is_array($result['data'][0])
        ? $result['data'][0]
        : null;
}

function fetchProfileByEmailOrFail(string $email): array
{
    $profile = stageArchiveFindProfileByEmail($email);
    if (!$profile) {
        seedFail("Profil introuvable pour {$email}");
    }

    return $profile;
}

function fetchStageByTitle(string $baseUrl, string $apiKey, string $title): ?array
{
    $result = supabaseRestRequest(
        'GET',
        $baseUrl . '/stages?title=eq.' . rawurlencode($title) . '&select=*&limit=1',
        $apiKey
    );

    return firstRow($result);
}

function createOrUpdateStage(string $baseUrl, string $apiKey, array $payload): array
{
    $existing = fetchStageByTitle($baseUrl, $apiKey, (string) $payload['title']);

    if ($existing) {
        $result = supabaseRestRequest(
            'PATCH',
            $baseUrl . '/stages?id=eq.' . (int) $existing['id'],
            $apiKey,
            $payload,
            ['Prefer: return=representation']
        );

        $stage = firstRow($result);
        if (!$result['ok'] || !$stage) {
            seedFail('Impossible de mettre a jour le stage ' . (string) $payload['title']);
        }

        seedInfo('Stage mis a jour : ' . (string) $payload['title']);
        return $stage;
    }

    $result = supabaseRestRequest(
        'POST',
        $baseUrl . '/stages',
        $apiKey,
        $payload,
        ['Prefer: return=representation']
    );

    $stage = firstRow($result);
    if (!$result['ok'] || !$stage) {
        seedFail('Impossible de creer le stage ' . (string) $payload['title']);
    }

    seedInfo('Stage cree : ' . (string) $payload['title']);
    return $stage;
}

function ensureMission(string $baseUrl, string $apiKey, int $stageId, int $companyId, string $title, string $description): void
{
    $lookup = supabaseRestRequest(
        'GET',
        $baseUrl . '/missions?stage_id=eq.' . $stageId . '&title=eq.' . rawurlencode($title) . '&select=id&limit=1',
        $apiKey
    );

    $existing = firstRow($lookup);

    if ($existing) {
        supabaseRestRequest(
            'PATCH',
            $baseUrl . '/missions?id=eq.' . (int) $existing['id'],
            $apiKey,
            [
                'description' => $description,
            ]
        );
        seedInfo('Mission deja presente : ' . $title);
        return;
    }

    $result = supabaseRestRequest(
        'POST',
        $baseUrl . '/missions',
        $apiKey,
        [
            'stage_id' => $stageId,
            'company_id' => $companyId,
            'title' => $title,
            'description' => $description,
        ]
    );

    if (!$result['ok']) {
        seedFail('Impossible de creer la mission ' . $title);
    }

    seedInfo('Mission creee : ' . $title);
}

function ensureCandidature(
    string $baseUrl,
    string $apiKey,
    int $stageId,
    int $studentId,
    string $status,
    ?string $cvUrl = null,
    ?string $coverLetterUrl = null
): array {
    $lookup = supabaseRestRequest(
        'GET',
        $baseUrl . '/candidatures?stage_id=eq.' . $stageId . '&student_id=eq.' . $studentId . '&select=*&limit=1',
        $apiKey
    );

    $existing = firstRow($lookup);
    $payload = [
        'status' => $status,
        'cv_url' => $cvUrl,
        'cover_letter_url' => $coverLetterUrl,
    ];

    if ($existing) {
        $result = supabaseRestRequest(
            'PATCH',
            $baseUrl . '/candidatures?id=eq.' . (int) $existing['id'],
            $apiKey,
            $payload,
            ['Prefer: return=representation']
        );
        $candidature = firstRow($result);
        if (!$result['ok'] || !$candidature) {
            seedFail('Impossible de mettre a jour une candidature de demo.');
        }

        seedInfo('Candidature mise a jour pour l etudiant #' . $studentId);
        return $candidature;
    }

    $result = supabaseRestRequest(
        'POST',
        $baseUrl . '/candidatures',
        $apiKey,
        array_merge(
            [
                'stage_id' => $stageId,
                'student_id' => $studentId,
            ],
            $payload
        ),
        ['Prefer: return=representation']
    );

    $candidature = firstRow($result);
    if (!$result['ok'] || !$candidature) {
        seedFail('Impossible de creer une candidature de demo.');
    }

    seedInfo('Candidature creee pour l etudiant #' . $studentId);
    return $candidature;
}

function ensureConvention(string $baseUrl, string $apiKey, int $stageId, int $studentId, bool $companyValidated): void
{
    $lookup = supabaseRestRequest(
        'GET',
        $baseUrl . '/conventions?stage_id=eq.' . $stageId . '&student_id=eq.' . $studentId . '&select=*&limit=1',
        $apiKey
    );

    $existing = firstRow($lookup);
    $payload = [
        'company_validated' => $companyValidated,
        'tutor_validated' => false,
        'admin_validated' => false,
    ];

    if ($existing) {
        $result = supabaseRestRequest(
            'PATCH',
            $baseUrl . '/conventions?id=eq.' . (int) $existing['id'],
            $apiKey,
            $payload
        );
        if (!$result['ok']) {
            seedFail('Impossible de mettre a jour la convention de demo.');
        }
        seedInfo('Convention mise a jour pour le stage #' . $stageId);
        return;
    }

    $result = supabaseRestRequest(
        'POST',
        $baseUrl . '/conventions',
        $apiKey,
        array_merge(
            [
                'stage_id' => $stageId,
                'student_id' => $studentId,
            ],
            $payload
        )
    );

    if (!$result['ok']) {
        seedFail('Impossible de creer la convention de demo.');
    }

    seedInfo('Convention creee pour le stage #' . $stageId);
}

function ensureRemark(string $baseUrl, string $apiKey, int $stageId, int $authorId, string $content): void
{
    $lookup = supabaseRestRequest(
        'GET',
        $baseUrl . '/remarques?stage_id=eq.' . $stageId . '&author_id=eq.' . $authorId . '&content=eq.' . rawurlencode($content) . '&select=id&limit=1',
        $apiKey
    );

    if (firstRow($lookup)) {
        seedInfo('Remarque deja presente pour le stage #' . $stageId);
        return;
    }

    $result = supabaseRestRequest(
        'POST',
        $baseUrl . '/remarques',
        $apiKey,
        [
            'stage_id' => $stageId,
            'author_id' => $authorId,
            'content' => $content,
        ]
    );

    if (!$result['ok']) {
        seedFail('Impossible de creer une remarque de demo.');
    }

    seedInfo('Remarque creee pour le stage #' . $stageId);
}

seedInfo('Creation des comptes de demo...');

foreach ($accounts as $account) {
    $existingProfile = stageArchiveFindProfileByEmail($account['email']);

    if ($existingProfile) {
        seedInfo('Compte deja present : ' . $account['email']);
        continue;
    }

    $result = addUserSupabase(
        $account['email'],
        $account['username'],
        $account['password'],
        $account['type']
    );

    if (is_array($result) && isset($result['code'])) {
        seedFail('Erreur creation compte ' . $account['email'] . ' : ' . (string) ($result['message'] ?? 'inconnue'));
    }

    seedInfo('Compte cree : ' . $account['email']);
}

$admin = fetchProfileByEmailOrFail('admin.demo@portfolium.fr');
$company = fetchProfileByEmailOrFail('entreprise.demo@portfolium.fr');
$studentA = fetchProfileByEmailOrFail('etudiant.demo@portfolium.fr');
$studentB = fetchProfileByEmailOrFail('etudiant2.demo@portfolium.fr');

seedInfo('Creation des stages de demo...');

$stageA = createOrUpdateStage(
    $baseUrl,
    $apiKey,
    [
        'title' => 'Developpeur Web Full Stack',
        'filiere' => 'informatique',
        'description' => 'Participation au developpement d une plateforme de suivi de stages avec PHP, SQL et integration d outils tiers.',
        'company' => (string) $company['username'],
        'location' => 'Cergy / Hybride',
        'start_date' => '2026-06-01',
        'end_date' => '2026-08-21',
        'duration_weeks' => 12,
        'status' => 'ouverte',
        'company_id' => (int) $company['id'],
        'student_id' => null,
    ]
);

$stageB = createOrUpdateStage(
    $baseUrl,
    $apiKey,
    [
        'title' => 'Analyste Data Junior',
        'filiere' => 'mathematiques',
        'description' => 'Analyse de donnees, creation de tableaux de bord et automatisation de rapports pour une equipe produit.',
        'company' => (string) $company['username'],
        'location' => 'Paris',
        'start_date' => '2026-06-15',
        'end_date' => '2026-09-04',
        'duration_weeks' => 12,
        'status' => 'ouverte',
        'company_id' => (int) $company['id'],
        'student_id' => null,
    ]
);

ensureMission($baseUrl, $apiKey, (int) $stageA['id'], (int) $company['id'], 'Developper une interface de suivi', 'Creer des pages de consultation, de candidature et de suivi des conventions.');
ensureMission($baseUrl, $apiKey, (int) $stageA['id'], (int) $company['id'], 'Connecter Supabase au projet', 'Manipuler l authentification, les profils utilisateurs et le stockage documentaire.');
ensureMission($baseUrl, $apiKey, (int) $stageB['id'], (int) $company['id'], 'Construire un tableau de bord', 'Produire des vues de synthese pour le pilotage des candidatures et du suivi des stages.');
ensureMission($baseUrl, $apiKey, (int) $stageB['id'], (int) $company['id'], 'Nettoyer et structurer des donnees', 'Travailler sur des exports de donnees et des indicateurs utiles a l equipe.');

seedInfo('Creation des candidatures et donnees de suivi...');

ensureCandidature(
    $baseUrl,
    $apiKey,
    (int) $stageA['id'],
    (int) $studentA['id'],
    'proposition envoyée'
);
ensureConvention($baseUrl, $apiKey, (int) $stageA['id'], (int) $studentA['id'], true);
ensureRemark(
    $baseUrl,
    $apiKey,
    (int) $stageA['id'],
    (int) $company['id'],
    'Premier entretien realise. Profil tres pertinent pour une mission full stack.'
);

ensureCandidature(
    $baseUrl,
    $apiKey,
    (int) $stageB['id'],
    (int) $studentB['id'],
    'en attente'
);

seedInfo('Seed de demo termine.');
