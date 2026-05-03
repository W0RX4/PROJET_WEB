<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../supabaseQuery/restClient.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$supabaseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/');
$apiKey = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = $supabaseUrl . '/rest/v1';
$bucket = 'candidatures';

if ($supabaseUrl === '' || $apiKey === '') {
    fwrite(STDERR, "SUPABASE_URL ou SUPABASE_KEY manquant dans .env\n");
    exit(1);
}

function apiUrl(string $table, string $query = ''): string
{
    global $baseUrl;
    return $baseUrl . '/' . $table . ($query !== '' ? '?' . $query : '');
}

function encoded(mixed $value): string
{
    return rawurlencode((string) $value);
}

function failIfNeeded(array $result, string $message): void
{
    if (!$result['ok']) {
        fwrite(STDERR, supabaseRestErrorMessage($result, $message) . "\n");
        fwrite(STDERR, ($result['raw'] ?? '') . "\n");
        exit(1);
    }
}

function firstRow(string $table, string $query): ?array
{
    global $apiKey;
    $result = supabaseRestRequest('GET', apiUrl($table, $query . '&limit=1'), $apiKey);
    failIfNeeded($result, "Lecture impossible dans $table.");
    $rows = is_array($result['data']) ? $result['data'] : [];
    return $rows[0] ?? null;
}

function insertRow(string $table, array $payload): array
{
    global $apiKey;
    $result = supabaseRestRequest(
        'POST',
        apiUrl($table),
        $apiKey,
        $payload,
        ['Prefer: return=representation']
    );
    failIfNeeded($result, "Insertion impossible dans $table.");
    $rows = is_array($result['data']) ? $result['data'] : [];
    return $rows[0] ?? [];
}

function patchRows(string $table, string $filter, array $payload): void
{
    global $apiKey;
    $result = supabaseRestRequest('PATCH', apiUrl($table, $filter), $apiKey, $payload);
    failIfNeeded($result, "Mise a jour impossible dans $table.");
}

function ensureUser(string $email, string $username, string $type): array
{
    $existing = firstRow('users', 'email=eq.' . encoded($email) . '&select=*');
    $payload = [
        'username' => $username,
        'email' => $email,
        'password' => 'DemoStage2026!',
        'type' => $type,
    ];

    if ($existing) {
        patchRows('users', 'id=eq.' . (int) $existing['id'], [
            'username' => $username,
            'type' => $type,
        ]);
        return array_merge($existing, ['username' => $username, 'type' => $type]);
    }

    return insertRow('users', $payload);
}

function ensureStage(string $title, array $payload): array
{
    $existing = firstRow('stages', 'title=eq.' . encoded($title) . '&select=*');
    $payload['title'] = $title;

    if ($existing) {
        patchRows('stages', 'id=eq.' . (int) $existing['id'], $payload);
        return array_merge($existing, $payload);
    }

    return insertRow('stages', $payload);
}

function ensureCandidature(int $studentId, int $stageId, array $payload): array
{
    $existing = firstRow(
        'candidatures',
        'student_id=eq.' . $studentId . '&stage_id=eq.' . $stageId . '&select=*'
    );
    $payload['student_id'] = $studentId;
    $payload['stage_id'] = $stageId;

    if ($existing) {
        patchRows('candidatures', 'id=eq.' . (int) $existing['id'], $payload);
        return array_merge($existing, $payload);
    }

    return insertRow('candidatures', $payload);
}

function ensureConvention(int $studentId, int $stageId, array $payload): array
{
    $existing = firstRow(
        'conventions',
        'student_id=eq.' . $studentId . '&stage_id=eq.' . $stageId . '&select=*'
    );
    $payload['student_id'] = $studentId;
    $payload['stage_id'] = $stageId;

    if ($existing) {
        patchRows('conventions', 'id=eq.' . (int) $existing['id'], $payload);
        return array_merge($existing, $payload);
    }

    return insertRow('conventions', $payload);
}

function ensureMission(int $stageId, int $companyId, string $title, string $description): void
{
    $existing = firstRow(
        'missions',
        'stage_id=eq.' . $stageId . '&title=eq.' . encoded($title) . '&select=id'
    );

    $payload = [
        'stage_id' => $stageId,
        'company_id' => $companyId,
        'title' => $title,
        'description' => $description,
    ];

    if ($existing) {
        patchRows('missions', 'id=eq.' . (int) $existing['id'], $payload);
        return;
    }

    insertRow('missions', $payload);
}

function ensureRemark(int $stageId, int $authorId, string $content): void
{
    $existing = firstRow(
        'remarques',
        'stage_id=eq.' . $stageId . '&content=eq.' . encoded($content) . '&select=id'
    );

    $payload = [
        'stage_id' => $stageId,
        'author_id' => $authorId,
        'content' => $content,
    ];

    if ($existing) {
        patchRows('remarques', 'id=eq.' . (int) $existing['id'], $payload);
        return;
    }

    insertRow('remarques', $payload);
}

function ensureCahierEntry(int $stageId, int $studentId, string $entryDate, string $content): void
{
    $existing = firstRow(
        'cahier_stage',
        'stage_id=eq.' . $stageId . '&student_id=eq.' . $studentId . '&entry_date=eq.' . $entryDate . '&select=id'
    );

    $payload = [
        'stage_id' => $stageId,
        'student_id' => $studentId,
        'entry_date' => $entryDate,
        'content' => $content,
    ];

    if ($existing) {
        patchRows('cahier_stage', 'id=eq.' . (int) $existing['id'], $payload);
        return;
    }

    insertRow('cahier_stage', $payload);
}

function ensureDocument(int $userId, int $stageId, string $type, string $path, string $fileName): void
{
    $existing = firstRow(
        'documents',
        'user_id=eq.' . $userId . '&stage_id=eq.' . $stageId . '&type=eq.' . encoded($type) . '&file_name=eq.' . encoded($fileName) . '&select=id'
    );

    $payload = [
        'user_id' => $userId,
        'stage_id' => $stageId,
        'type' => $type,
        'file_path' => $path,
        'file_name' => $fileName,
    ];

    if ($existing) {
        patchRows('documents', 'id=eq.' . (int) $existing['id'], $payload);
        return;
    }

    insertRow('documents', $payload);
}

function pdfEscape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

function demoPdf(string $title, array $lines): string
{
    $stream = "BT\n/F1 12 Tf\n72 760 Td\n(" . pdfEscape($title) . ") Tj\n";
    foreach ($lines as $line) {
        $stream .= "0 -18 Td\n(" . pdfEscape($line) . ") Tj\n";
    }
    $stream .= "ET\n";

    $objects = [
        '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
        '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
        '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
        '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        '5 0 obj << /Length ' . strlen($stream) . " >> stream\n" . $stream . 'endstream endobj',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF\n";

    return $pdf;
}

function uploadDemoPdf(string $path, string $title, array $lines): string
{
    global $supabaseUrl, $apiKey, $bucket;

    $url = $supabaseUrl . '/storage/v1/object/' . $bucket . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => demoPdf($title, $lines),
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/pdf',
            'x-upsert: true',
        ],
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error !== '' || $code < 200 || $code >= 300) {
        fwrite(STDERR, "Upload Storage impossible pour $path ($code $error) $response\n");
        exit(1);
    }

    return $path;
}

function demoPath(string $group, string $name): string
{
    return 'demo-prof/' . $group . '/' . $name;
}

$company = ensureUser('entreprise.demo@portfolium.fr', 'TechNova', 'entreprise');
$companyId = (int) $company['id'];

$tutor = firstRow('users', 'type=eq.tuteur&select=id,username,email,type');
$tutorId = $tutor ? (int) $tutor['id'] : null;

$filieres = [
    'Informatique',
    'Data IA',
    'Cybersecurite',
    'Systemes embarques',
    'Reseaux',
    'Genie logiciel',
    'Business intelligence',
    'UX design',
    'DevOps cloud',
    'Securite applicative',
];

$archiveTitles = [
    'Migration intranet finalisee',
    'Refonte portail RH archivee',
    'Audit data warehouse cloture',
    'Prototype IoT livre',
    'Supervision reseau stabilisee',
    'Application CRM maintenue',
    'Pipeline BI industrialise',
    'Recherche UX finalisee',
    'Automatisation CloudOps cloturee',
    'Audit securite applicative archive',
];

$pendingTitles = [
    'Assistant developpement API',
    'Analyste reporting commercial',
    'Technicien securite SI',
    'Developpeur interfaces internes',
    'Assistant infrastructure reseau',
    'Charge integration logiciel',
    'Assistant data visualisation',
    'Product designer junior',
    'Assistant plateforme cloud',
    'Testeur qualite applicative',
];

$validatedTitles = [
    'Module facturation valide',
    'Dashboard finance valide',
    'Plan de durcissement valide',
    'Outil support interne valide',
    'Cartographie reseau validee',
    'Connecteur ERP valide',
    'Datamart ventes valide',
    'Parcours utilisateur valide',
    'Deploiement Kubernetes valide',
    'Revue OWASP validee',
];

$summary = [
    'archives' => 0,
    'demandes' => 0,
    'stages_valides' => 0,
    'documents' => 0,
];

for ($i = 1; $i <= 10; $i++) {
    $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $student = ensureUser(
        "demo.archive.$num@portfolium.fr",
        "Archive Demo $num",
        'etudiant'
    );
    $studentId = (int) $student['id'];
    $title = '[ARCHIVE DEMO] ' . $archiveTitles[$i - 1];
    $stage = ensureStage($title, [
        'filiere' => $filieres[$i - 1],
        'description' => "Dossier archive de demonstration: livrables, suivi entreprise et validation finale disponibles pour la soutenance.",
        'company' => 'TechNova',
        'location' => ['Cergy', 'Paris', 'Lyon', 'Nantes', 'Toulouse'][$i % 5],
        'start_date' => '2025-' . str_pad((string) (($i % 5) + 1), 2, '0', STR_PAD_LEFT) . '-03',
        'end_date' => '2025-' . str_pad((string) (($i % 5) + 6), 2, '0', STR_PAD_LEFT) . '-21',
        'student_id' => $studentId,
        'tutor_id' => $tutorId,
        'company_id' => $companyId,
        'competences' => 'Gestion de projet, documentation, tests, communication',
        'duration_weeks' => 20,
        'status' => 'archivée',
    ]);
    $stageId = (int) $stage['id'];
    patchRows('users', 'id=eq.' . $studentId, ['stage_id' => $stageId]);
    ensureCandidature($studentId, $stageId, ['status' => 'convention envoyée']);
    ensureConvention($studentId, $stageId, [
        'company_validated' => true,
        'tutor_validated' => true,
        'admin_validated' => true,
    ]);
    ensureMission($stageId, $companyId, 'Bilan technique', 'Formaliser les resultats obtenus et les points de reprise.');
    ensureMission($stageId, $companyId, 'Passation', 'Preparer les livrables et la documentation d exploitation.');
    ensureRemark($stageId, $companyId, "Dossier archive complet pour la demo professeur $num.");
    ensureCahierEntry($stageId, $studentId, '2025-05-16', 'Synthese finale: objectifs atteints, livrables remis et soutenance preparee.');

    foreach (['convention', 'rapport', 'fiche_evaluation'] as $docType) {
        $fileName = $docType . "_archive_$num.pdf";
        $path = uploadDemoPdf(
            demoPath('archives', $fileName),
            strtoupper($docType) . " ARCHIVE $num",
            ['Etudiant: Archive Demo ' . $num, 'Entreprise: TechNova', 'Statut: dossier archive']
        );
        ensureDocument($studentId, $stageId, $docType, $path, $fileName);
        $summary['documents']++;
    }
    $summary['archives']++;
}

for ($i = 1; $i <= 10; $i++) {
    $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $student = ensureUser(
        "demo.demande.$num@portfolium.fr",
        "Candidat Demo $num",
        'etudiant'
    );
    $studentId = (int) $student['id'];
    $title = '[DEMANDE DEMO] ' . $pendingTitles[$i - 1];
    $stage = ensureStage($title, [
        'filiere' => $filieres[$i - 1],
        'description' => "Offre ouverte avec candidature en attente pour montrer le traitement des demandes de stage.",
        'company' => 'TechNova',
        'location' => ['Cergy', 'Paris', 'Lyon', 'Nantes', 'Toulouse'][$i % 5],
        'start_date' => '2026-' . str_pad((string) (($i % 4) + 6), 2, '0', STR_PAD_LEFT) . '-01',
        'end_date' => '2026-' . str_pad((string) (($i % 4) + 9), 2, '0', STR_PAD_LEFT) . '-18',
        'student_id' => null,
        'tutor_id' => null,
        'company_id' => $companyId,
        'competences' => 'Curiosite, bases techniques, autonomie',
        'duration_weeks' => 12,
        'status' => 'ouverte',
    ]);
    $stageId = (int) $stage['id'];
    ensureMission($stageId, $companyId, 'Analyse du besoin', 'Comprendre le contexte et cadrer les livrables attendus.');
    ensureMission($stageId, $companyId, 'Realisation encadree', 'Produire une premiere version exploitable par l equipe.');

    $cvPath = uploadDemoPdf(
        demoPath('demandes', "cv_demande_$num.pdf"),
        "CV DEMANDE $num",
        ['Candidat: Candidat Demo ' . $num, 'Competences: PHP, SQL, Git', 'Disponibilite: ete 2026']
    );
    $letterPath = uploadDemoPdf(
        demoPath('demandes', "lettre_demande_$num.pdf"),
        "LETTRE DEMANDE $num",
        ['Motivation pour TechNova', 'Projet professionnel: stage technique encadre']
    );
    ensureCandidature($studentId, $stageId, [
        'status' => 'en attente',
        'cv_url' => $cvPath,
        'cover_letter_url' => $letterPath,
    ]);
    $summary['documents'] += 2;
    $summary['demandes']++;
}

for ($i = 1; $i <= 10; $i++) {
    $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $student = ensureUser(
        "demo.fini.$num@portfolium.fr",
        "Stage Valide $num",
        'etudiant'
    );
    $studentId = (int) $student['id'];
    $title = '[VALIDE DEMO] ' . $validatedTitles[$i - 1];
    $stage = ensureStage($title, [
        'filiere' => $filieres[$i - 1],
        'description' => "Stage termine et convention entierement validee pour tester les filtres admin et l archivage.",
        'company' => 'TechNova',
        'location' => ['Cergy', 'Paris', 'Lyon', 'Nantes', 'Toulouse'][$i % 5],
        'start_date' => '2025-' . str_pad((string) (($i % 4) + 8), 2, '0', STR_PAD_LEFT) . '-02',
        'end_date' => '2026-' . str_pad((string) (($i % 3) + 1), 2, '0', STR_PAD_LEFT) . '-24',
        'student_id' => $studentId,
        'tutor_id' => $tutorId,
        'company_id' => $companyId,
        'competences' => 'Conception, developpement, recette, restitution',
        'duration_weeks' => 18,
        'status' => 'fermée',
    ]);
    $stageId = (int) $stage['id'];
    patchRows('users', 'id=eq.' . $studentId, ['stage_id' => $stageId]);
    ensureCandidature($studentId, $stageId, ['status' => 'convention envoyée']);
    ensureConvention($studentId, $stageId, [
        'company_validated' => true,
        'tutor_validated' => true,
        'admin_validated' => true,
    ]);
    ensureMission($stageId, $companyId, 'Recette finale', 'Valider les fonctionnalites avec les utilisateurs internes.');
    ensureMission($stageId, $companyId, 'Soutenance', 'Preparer le support de bilan et les indicateurs de resultat.');
    ensureRemark($stageId, $companyId, "Stage termine et valide pour la demo professeur $num.");
    ensureCahierEntry($stageId, $studentId, '2026-01-12', 'Bilan final: recette effectuee, corrections integrees, convention validee.');

    foreach (['convention', 'rapport', 'fiche_evaluation'] as $docType) {
        $fileName = $docType . "_valide_$num.pdf";
        $path = uploadDemoPdf(
            demoPath('valides', $fileName),
            strtoupper($docType) . " VALIDE $num",
            ['Etudiant: Stage Valide ' . $num, 'Entreprise: TechNova', 'Statut: stage termine et valide']
        );
        ensureDocument($studentId, $stageId, $docType, $path, $fileName);
        $summary['documents']++;
    }
    $summary['stages_valides']++;
}

echo "Seed professeur termine.\n";
echo "- Archives ajoutees/actualisees: {$summary['archives']}\n";
echo "- Demandes de stage ajoutees/actualisees: {$summary['demandes']}\n";
echo "- Stages finis et valides ajoutes/actualises: {$summary['stages_valides']}\n";
echo "- Documents PDF de demo deposes/actualises: {$summary['documents']}\n";
