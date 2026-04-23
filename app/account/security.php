<?php
require_once __DIR__ . '/../../connection/authSession.php';
require_once __DIR__ . '/../../supabaseQuery/authClient.php';

stageArchiveStartSession();
supabaseAuthEnsureEnvLoaded();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$accessToken = (string) ($_SESSION['supabase_access_token'] ?? '');
$refreshToken = (string) ($_SESSION['supabase_refresh_token'] ?? '');
$authUserId = (string) ($_SESSION['auth_user_id'] ?? '');
$supabaseUrl = rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/');
$supabaseAnonKey = (string) ($_ENV['SUPABASE_ANON_KEY'] ?? '');
$canManageTotpInBrowser = $supabaseUrl !== '' && $supabaseAnonKey !== '' && $accessToken !== '';

if ($accessToken === '' || $authUserId === '') {
    $_SESSION['error'] = 'Votre session Supabase est incomplete. Reconnectez-vous.';
    header('Location: /login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'refresh_session_after_mfa') {
        $_SESSION['supabase_access_token'] = (string) ($_POST['access_token'] ?? $accessToken);
        $_SESSION['supabase_refresh_token'] = (string) ($_POST['refresh_token'] ?? $refreshToken);
        $_SESSION['supabase_token_expires_at'] = time() + (int) ($_POST['expires_in'] ?? 3600);
        $_SESSION['success'] = 'Double authentification activee avec Supabase.';
        header('Location: /app/account/security.php');
        exit;
    }

    if ($action === 'unenroll_factor') {
        $factorId = (string) ($_POST['factor_id'] ?? '');

        if ($factorId === '') {
            $_SESSION['error'] = 'Facteur MFA invalide.';
            header('Location: /app/account/security.php');
            exit;
        }

        $deleteResult = supabaseAuthDeleteFactor($accessToken, $factorId);
        if (!$deleteResult['ok']) {
            $_SESSION['error'] = supabaseAuthErrorMessage($deleteResult, 'Impossible de supprimer ce facteur MFA.');
            header('Location: /app/account/security.php');
            exit;
        }

        if ($refreshToken !== '') {
            $refreshResult = supabaseAuthRefreshSession($refreshToken);
            if ($refreshResult['ok'] && is_array($refreshResult['data'] ?? null)) {
                $_SESSION['supabase_access_token'] = (string) ($refreshResult['data']['access_token'] ?? $accessToken);
                $_SESSION['supabase_refresh_token'] = (string) ($refreshResult['data']['refresh_token'] ?? $refreshToken);
                $_SESSION['supabase_token_expires_at'] = time() + (int) ($refreshResult['data']['expires_in'] ?? 3600);
            }
        }

        $_SESSION['success'] = 'Facteur MFA supprime.';
        header('Location: /app/account/security.php');
        exit;
    }
}

$factorsResult = supabaseAuthAdminListUserFactors($authUserId);
$factors = is_array($factorsResult['data']) ? $factorsResult['data'] : [];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <h2>Securite du compte</h2>
    <p>La connexion et la double authentification reposent maintenant sur Supabase Auth. Les regles MFA se pilotent depuis l onglet <strong>Authentication</strong> de Supabase, et l activation TOTP peut se faire ici quand la cle publique Supabase est disponible cote navigateur.</p>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_SESSION['success']); ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($_SESSION['error']); ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (!$canManageTotpInBrowser): ?>
    <div class="alert alert-error">
        Ajoutez <code>SUPABASE_ANON_KEY</code> dans votre fichier <code>.env</code> pour permettre l enrollement TOTP depuis le navigateur sans exposer la cle secrete serveur.
    </div>
<?php endif; ?>

<div class="grid-container">
    <div class="card">
        <h3>Facteurs actifs</h3>
        <?php if (!$factorsResult['ok']): ?>
            <p>Impossible de charger vos facteurs MFA.</p>
        <?php elseif (empty($factors)): ?>
            <p>Aucun facteur MFA n est actuellement configure.</p>
        <?php else: ?>
            <?php foreach ($factors as $factor): ?>
                <?php
                    $factorId = (string) ($factor['id'] ?? '');
                    $factorType = (string) ($factor['factor_type'] ?? $factor['type'] ?? 'mfa');
                    $factorStatus = (string) ($factor['status'] ?? 'unknown');
                    $factorName = (string) ($factor['friendly_name'] ?? $factor['name'] ?? $factorType);
                ?>
                <div class="card" style="margin-top: 1rem;">
                    <p><strong><?php echo htmlspecialchars($factorName); ?></strong></p>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                        Type: <?php echo htmlspecialchars($factorType); ?> | Statut: <?php echo htmlspecialchars($factorStatus); ?>
                    </p>
                    <form method="post">
                        <input type="hidden" name="action" value="unenroll_factor">
                        <input type="hidden" name="factor_id" value="<?php echo htmlspecialchars($factorId); ?>">
                        <button type="submit" class="btn" style="background: var(--danger-color); color: white;">Supprimer ce facteur</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Activer le TOTP</h3>
        <p>Ajoutez une application d authentification comme Google Authenticator, 1Password ou Authy.</p>

        <?php if ($canManageTotpInBrowser): ?>
            <div id="mfa-feedback" class="alert alert-error" style="display: none;"></div>

            <div class="form-group">
                <label class="form-label" for="friendly_name">Nom du facteur</label>
                <input type="text" id="friendly_name" class="form-control" value="StageArchive">
            </div>
            <button type="button" class="btn btn-primary" id="start-totp-enrollment">Generer un QR code</button>

            <div id="mfa-enrollment-panel" style="display: none; margin-top: 1.5rem;">
                <p>Scannez ce QR code dans votre application, puis saisissez un code genere pour finaliser l activation.</p>

                <div style="background: white; border-radius: 12px; padding: 1rem; display: inline-block; margin-bottom: 1rem;">
                    <img id="mfa-qr-code" alt="QR code MFA Supabase" style="max-width: 220px; width: 100%; height: auto;">
                </div>

                <p><strong>Secret manuel :</strong> <code id="mfa-secret"></code></p>
                <p style="word-break: break-all;"><strong>URI :</strong> <code id="mfa-uri"></code></p>

                <div class="form-group">
                    <label class="form-label" for="totp_code">Code TOTP</label>
                    <input type="text" id="totp_code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456">
                </div>
                <button type="button" class="btn btn-primary" id="verify-totp-enrollment">Confirmer le facteur</button>
            </div>

            <form method="post" id="mfa-sync-form" style="display: none;">
                <input type="hidden" name="action" value="refresh_session_after_mfa">
                <input type="hidden" name="access_token" id="sync_access_token">
                <input type="hidden" name="refresh_token" id="sync_refresh_token">
                <input type="hidden" name="expires_in" id="sync_expires_in">
            </form>
        <?php else: ?>
            <p>Activez la MFA dans Supabase puis ajoutez la cle publique <code>SUPABASE_ANON_KEY</code> a l environnement pour terminer l enrollement depuis le site.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManageTotpInBrowser): ?>
<script>
(function () {
    const supabaseUrl = <?php echo json_encode($supabaseUrl); ?>;
    const anonKey = <?php echo json_encode($supabaseAnonKey); ?>;
    let accessToken = <?php echo json_encode($accessToken); ?>;
    let currentFactorId = '';

    const feedback = document.getElementById('mfa-feedback');
    const panel = document.getElementById('mfa-enrollment-panel');
    const qrCode = document.getElementById('mfa-qr-code');
    const secret = document.getElementById('mfa-secret');
    const uri = document.getElementById('mfa-uri');
    const syncForm = document.getElementById('mfa-sync-form');

    function showError(message) {
        if (!feedback) {
            return;
        }

        feedback.style.display = 'block';
        feedback.className = 'alert alert-error';
        feedback.textContent = message;
    }

    function clearError() {
        if (!feedback) {
            return;
        }

        feedback.style.display = 'none';
        feedback.textContent = '';
    }

    function normalizeQrCodeSrc(value) {
        if (!value) {
            return '';
        }

        if (value.startsWith('data:') || value.startsWith('http://') || value.startsWith('https://')) {
            return value;
        }

        if (value.includes('<svg')) {
            return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(value);
        }

        return value;
    }

    async function authFetch(path, payload) {
        const response = await fetch(supabaseUrl + path, {
            method: 'POST',
            headers: {
                'apikey': anonKey,
                'Authorization': 'Bearer ' + accessToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: payload ? JSON.stringify(payload) : undefined
        });

        const data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok) {
            throw new Error(data.msg || data.message || data.error_description || 'Erreur MFA Supabase.');
        }

        return data;
    }

    document.getElementById('start-totp-enrollment').addEventListener('click', async function () {
        clearError();

        try {
            const friendlyName = document.getElementById('friendly_name').value || 'StageArchive';
            const result = await authFetch('/auth/v1/factors', {
                factor_type: 'totp',
                friendly_name: friendlyName
            });

            currentFactorId = result.id || '';
            if (!currentFactorId || !result.totp) {
                throw new Error('Reponse Supabase invalide pendant l enrollement MFA.');
            }

            qrCode.src = normalizeQrCodeSrc(result.totp.qr_code || '');
            secret.textContent = result.totp.secret || '';
            uri.textContent = result.totp.uri || '';
            panel.style.display = 'block';
        } catch (error) {
            showError(error.message);
        }
    });

    document.getElementById('verify-totp-enrollment').addEventListener('click', async function () {
        clearError();

        try {
            const code = (document.getElementById('totp_code').value || '').replace(/\D/g, '');
            if (code.length !== 6) {
                throw new Error('Le code TOTP doit contenir 6 chiffres.');
            }

            if (!currentFactorId) {
                throw new Error('Aucun facteur MFA en attente de verification.');
            }

            const challenge = await authFetch('/auth/v1/factors/' + encodeURIComponent(currentFactorId) + '/challenge');
            const challengeId = challenge.id || challenge.challenge_id || '';
            if (!challengeId) {
                throw new Error('Impossible de creer le challenge MFA.');
            }

            const verified = await authFetch('/auth/v1/factors/' + encodeURIComponent(currentFactorId) + '/verify', {
                challenge_id: challengeId,
                code: code
            });

            document.getElementById('sync_access_token').value = verified.access_token || accessToken;
            document.getElementById('sync_refresh_token').value = verified.refresh_token || '';
            document.getElementById('sync_expires_in').value = verified.expires_in || 3600;
            syncForm.submit();
        } catch (error) {
            showError(error.message);
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
