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
];

$legacyDemoEmails = [
    'etudiant2.demo@portfolium.fr',
    'tuteur.demo@portfolium.fr',
    'jury.demo@portfolium.fr',
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

function deleteLegacyDemoAccount(string $baseUrl, string $apiKey, string $email, int $handoverUserId): void
{
    $profile = stageArchiveFindProfileByEmail($email);

    if ($profile) {
        $userId = (int) ($profile['id'] ?? 0);
        $isStudent = ($profile['type'] ?? '') === 'etudiant';

        if ($userId > 0) {
            $cleanupSteps = [
                [
                    'method' => 'PATCH',
                    'url' => $baseUrl . '/stages?student_id=eq.' . $userId,
                    'payload' => $isStudent ? ['student_id' => null, 'status' => 'archivée'] : ['student_id' => null],
                    'message' => 'Impossible de detacher les stages du compte demo ' . $email,
                ],
                [
                    'method' => 'DELETE',
                    'url' => $baseUrl . '/candidatures?student_id=eq.' . $userId,
                    'payload' => null,
                    'message' => 'Impossible de supprimer les candidatures du compte demo ' . $email,
                ],
                [
                    'method' => 'DELETE',
                    'url' => $baseUrl . '/conventions?student_id=eq.' . $userId,
                    'payload' => null,
                    'message' => 'Impossible de supprimer les conventions du compte demo ' . $email,
                ],
                [
                    'method' => 'DELETE',
                    'url' => $baseUrl . '/formation_requests?student_id=eq.' . $userId,
                    'payload' => null,
                    'message' => 'Impossible de supprimer les demandes de formation du compte demo ' . $email,
                ],
                [
                    'method' => 'DELETE',
                    'url' => $baseUrl . '/cahier_stage?student_id=eq.' . $userId,
                    'payload' => null,
                    'message' => 'Impossible de supprimer le cahier du compte demo ' . $email,
                ],
                [
                    'method' => 'DELETE',
                    'url' => $baseUrl . '/two_factor_codes?user_id=eq.' . $userId,
                    'payload' => null,
                    'message' => 'Impossible de supprimer les codes MFA du compte demo ' . $email,
                ],
                [
                    'method' => 'PATCH',
                    'url' => $baseUrl . '/documents?user_id=eq.' . $userId,
                    'payload' => ['user_id' => $handoverUserId],
                    'message' => 'Impossible de rattacher les documents du compte demo ' . $email,
                ],
                [
                    'method' => 'PATCH',
                    'url' => $baseUrl . '/remarques?author_id=eq.' . $userId,
                    'payload' => ['author_id' => $handoverUserId],
                    'message' => 'Impossible de rattacher les remarques du compte demo ' . $email,
                ],
                [
                    'method' => 'PATCH',
                    'url' => $baseUrl . '/missions?company_id=eq.' . $userId,
                    'payload' => ['company_id' => $handoverUserId],
                    'message' => 'Impossible de rattacher les missions du compte demo ' . $email,
                ],
                [
                    'method' => 'PATCH',
                    'url' => $baseUrl . '/traces?user_id=eq.' . $userId,
                    'payload' => ['user_id' => null],
                    'message' => 'Impossible de nettoyer les traces du compte demo ' . $email,
                ],
                [
                    'method' => 'PATCH',
                    'url' => $baseUrl . '/stages?tutor_id=eq.' . $userId,
                    'payload' => ['tutor_id' => null],
                    'message' => 'Impossible de detacher les tutorats du compte demo ' . $email,
                ],
                [
                    'method' => 'PATCH',
                    'url' => $baseUrl . '/stages?company_id=eq.' . $userId,
                    'payload' => ['company_id' => null],
                    'message' => 'Impossible de detacher les stages entreprise du compte demo ' . $email,
                ],
                [
                    'method' => 'PATCH',
                    'url' => $baseUrl . '/users?id=eq.' . $userId,
                    'payload' => ['stage_id' => null],
                    'message' => 'Impossible de nettoyer le profil demo ' . $email,
                ],
            ];

            foreach ($cleanupSteps as $step) {
                $result = supabaseRestRequest($step['method'], $step['url'], $apiKey, $step['payload']);
                if (!$result['ok']) {
                    seedFail(supabaseRestErrorMessage($result, $step['message']));
                }
            }

            $deleteProfile = supabaseRestRequest('DELETE', $baseUrl . '/users?id=eq.' . $userId, $apiKey);
            if (!$deleteProfile['ok']) {
                seedFail(supabaseRestErrorMessage($deleteProfile, 'Impossible de supprimer le profil demo ' . $email));
            }
        }
    }

    $authUser = supabaseAuthAdminFindUserByEmail($email);
    if ($authUser && !empty($authUser['id'])) {
        $deleteAuth = supabaseAuthAdminDeleteUser((string) $authUser['id']);
        if (!$deleteAuth['ok']) {
            seedFail(supabaseAuthErrorMessage($deleteAuth, 'Impossible de supprimer le compte Auth demo ' . $email));
        }
    }

    if ($profile || $authUser) {
        seedInfo('Ancien compte demo retire : ' . $email);
    }
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

foreach ($legacyDemoEmails as $legacyEmail) {
    deleteLegacyDemoAccount($baseUrl, $apiKey, $legacyEmail, (int) $admin['id']);
}

seedInfo('Creation des stages de demo...');

$demoStages = [
    [
        'payload' => [
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
        ],
        'missions' => [
            ['Developper une interface de suivi', 'Creer des pages de consultation, de candidature et de suivi des conventions.'],
            ['Connecter Supabase au projet', 'Manipuler l authentification, les profils utilisateurs et le stockage documentaire.'],
        ],
        'candidature_status' => 'proposition envoyée',
        'convention' => true,
        'remark' => 'Premier entretien realise. Profil tres pertinent pour une mission full stack.',
    ],
    [
        'payload' => [
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
        ],
        'missions' => [
            ['Construire un tableau de bord', 'Produire des vues de synthese pour le pilotage des candidatures et du suivi des stages.'],
            ['Nettoyer et structurer des donnees', 'Travailler sur des exports de donnees et des indicateurs utiles a l equipe.'],
        ],
        'candidature_status' => 'en attente',
    ],
    [
        'payload' => [
            'title' => 'Assistant Cybersecurite',
            'filiere' => 'informatique',
            'description' => 'Renforcement de la securite applicative, revue des acces et preparation de recommandations pour une application web interne.',
            'company' => (string) $company['username'],
            'location' => 'La Defense',
            'start_date' => '2026-06-08',
            'end_date' => '2026-08-28',
            'duration_weeks' => 12,
            'status' => 'ouverte',
            'company_id' => (int) $company['id'],
            'student_id' => null,
        ],
        'missions' => [
            ['Auditer les permissions', 'Verifier les roles, les droits et les parcours sensibles de la plateforme.'],
            ['Rediger un plan de durcissement', 'Prioriser les corrections et proposer des controles simples a maintenir.'],
        ],
        'candidature_status' => 'en attente',
    ],
    [
        'payload' => [
            'title' => 'Ingenieur DevOps Cloud',
            'filiere' => 'informatique',
            'description' => 'Mise en place d automatisations de deploiement, suivi des environnements et amelioration des controles de livraison.',
            'company' => (string) $company['username'],
            'location' => 'Remote / Paris',
            'start_date' => '2026-07-01',
            'end_date' => '2026-09-18',
            'duration_weeks' => 12,
            'status' => 'ouverte',
            'company_id' => (int) $company['id'],
            'student_id' => null,
        ],
        'missions' => [
            ['Automatiser les deploiements', 'Creer des scripts de livraison et documenter les etapes critiques.'],
            ['Surveiller les environnements', 'Mettre en place des indicateurs simples pour detecter les erreurs de service.'],
        ],
    ],
    [
        'payload' => [
            'title' => 'UX UI Designer Produit',
            'filiere' => 'design numerique',
            'description' => 'Conception de parcours utilisateurs, maquettage d interfaces et tests rapides avec des utilisateurs internes.',
            'company' => (string) $company['username'],
            'location' => 'Cergy',
            'start_date' => '2026-06-22',
            'end_date' => '2026-09-11',
            'duration_weeks' => 12,
            'status' => 'ouverte',
            'company_id' => (int) $company['id'],
            'student_id' => null,
        ],
        'missions' => [
            ['Cartographier les parcours', 'Identifier les irritants dans les ecrans de suivi des candidatures.'],
            ['Produire des maquettes', 'Proposer des interfaces claires pour les roles etudiant et entreprise.'],
        ],
    ],
    [
        'payload' => [
            'title' => 'Developpeur Mobile Flutter',
            'filiere' => 'informatique',
            'description' => 'Prototype mobile pour consulter les offres, suivre ses candidatures et recevoir des notifications de progression.',
            'company' => (string) $company['username'],
            'location' => 'Lyon / Hybride',
            'start_date' => '2026-07-06',
            'end_date' => '2026-09-25',
            'duration_weeks' => 12,
            'status' => 'ouverte',
            'company_id' => (int) $company['id'],
            'student_id' => null,
        ],
        'missions' => [
            ['Construire les ecrans principaux', 'Lister les offres, afficher le detail et suivre les candidatures.'],
            ['Brancher les donnees de test', 'Consommer l API Supabase et gerer les etats de chargement.'],
        ],
    ],
    [
        'payload' => [
            'title' => 'Charge de Projet IA',
            'filiere' => 'intelligence artificielle',
            'description' => 'Exploration de cas d usage IA pour aider les equipes a classer les candidatures et synthetiser les retours.',
            'company' => (string) $company['username'],
            'location' => 'Paris / Remote',
            'start_date' => '2026-06-29',
            'end_date' => '2026-09-18',
            'duration_weeks' => 12,
            'status' => 'ouverte',
            'company_id' => (int) $company['id'],
            'student_id' => null,
        ],
        'missions' => [
            ['Identifier les cas d usage', 'Comparer les besoins des etudiants, entreprises et administrateurs.'],
            ['Prototyper une synthese', 'Produire un premier flux de resume pour les candidatures et remarques.'],
        ],
        'candidature_status' => 'refusée par l\'étudiant',
    ],
];

$stagesByTitle = [];
foreach ($demoStages as $stageConfig) {
    $stage = createOrUpdateStage($baseUrl, $apiKey, $stageConfig['payload']);
    $stagesByTitle[(string) $stageConfig['payload']['title']] = $stage;

    foreach ($stageConfig['missions'] as $mission) {
        ensureMission(
            $baseUrl,
            $apiKey,
            (int) $stage['id'],
            (int) $company['id'],
            $mission[0],
            $mission[1]
        );
    }
}

seedInfo('Creation des candidatures et donnees de suivi...');

foreach ($demoStages as $stageConfig) {
    if (empty($stageConfig['candidature_status'])) {
        continue;
    }

    $stage = $stagesByTitle[(string) $stageConfig['payload']['title']];
    ensureCandidature(
        $baseUrl,
        $apiKey,
        (int) $stage['id'],
        (int) $studentA['id'],
        (string) $stageConfig['candidature_status']
    );

    if (!empty($stageConfig['convention'])) {
        ensureConvention($baseUrl, $apiKey, (int) $stage['id'], (int) $studentA['id'], true);
    }

    if (!empty($stageConfig['remark'])) {
        ensureRemark(
            $baseUrl,
            $apiKey,
            (int) $stage['id'],
            (int) $company['id'],
            (string) $stageConfig['remark']
        );
    }
}

seedInfo('Seed de demo termine.');
