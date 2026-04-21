<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['pending_2fa'])) {
        header('Location: /login');
        exit;
    }

    $emailTarget = $_SESSION['pending_2fa']['email'] ?? '';
    $maskedEmail = '';
    if ($emailTarget) {
        $parts = explode('@', $emailTarget);
        if (count($parts) === 2) {
            $name = $parts[0];
            $visible = substr($name, 0, 2);
            $maskedEmail = $visible . str_repeat('*', max(1, strlen($name) - 2)) . '@' . $parts[1];
        }
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification - StageArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .otp-inputs {
            display: flex;
            gap: 0.6rem;
            justify-content: center;
            margin: 1.5rem 0;
        }
        .otp-inputs input {
            width: 48px;
            height: 58px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 1.5px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: var(--surface-color);
            color: var(--text-primary);
            transition: all var(--transition);
        }
        .otp-inputs input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12), 0 0 20px rgba(139, 92, 246, 0.15);
            transform: translateY(-2px);
        }
        @media (max-width: 480px) {
            .otp-inputs { gap: 0.35rem; }
            .otp-inputs input { width: 40px; height: 50px; font-size: 1.25rem; }
        }
        .resend-link {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .resend-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card">
            <h1 class="auth-title">StageArchive</h1>
            <h2 class="text-center mb-4" style="color: var(--text-secondary); font-weight: 500; font-size: 1rem;">Verification en 2 etapes</h2>

            <p class="text-center" style="margin-bottom: 0.5rem;">
                Code envoye a <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($maskedEmail); ?></strong>
            </p>

            <?php
                if (isset($_SESSION['error'])) {
                    echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['error']) . "</div>";
                    unset($_SESSION['error']);
                }
                if (isset($_SESSION['success'])) {
                    echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['success']) . "</div>";
                    unset($_SESSION['success']);
                }
            ?>

            <form action="/verify-2fa" method="post" id="otp-form">
                <input type="hidden" name="code" id="code-hidden">

                <div class="otp-inputs">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" autocomplete="one-time-code" autofocus>
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1">
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1">
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-4">Verifier</button>
            </form>

            <div class="text-center mt-4">
                <a href="/resend-2fa" class="resend-link">Renvoyer le code</a>
                <span style="color: var(--text-secondary); margin: 0 0.5rem;">&middot;</span>
                <a href="/logout" class="resend-link" style="color: var(--danger-color);">Annuler</a>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var inputs = document.querySelectorAll('.otp-inputs input');
        var hidden = document.getElementById('code-hidden');
        var form = document.getElementById('otp-form');

        function collect() {
            var s = '';
            inputs.forEach(function(i) { s += i.value || ''; });
            hidden.value = s;
            return s;
        }

        inputs.forEach(function(input, idx) {
            input.addEventListener('input', function(e) {
                input.value = input.value.replace(/[^0-9]/g, '').slice(0, 1);
                if (input.value && idx < inputs.length - 1) {
                    inputs[idx + 1].focus();
                }
                if (collect().length === inputs.length) {
                    form.submit();
                }
            });
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !input.value && idx > 0) {
                    inputs[idx - 1].focus();
                }
            });
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, inputs.length);
                for (var i = 0; i < pasted.length && i < inputs.length; i++) {
                    inputs[i].value = pasted[i];
                }
                if (pasted.length === inputs.length) {
                    collect();
                    form.submit();
                } else if (inputs[pasted.length]) {
                    inputs[pasted.length].focus();
                }
            });
        });

        form.addEventListener('submit', function() { collect(); });
    })();
    </script>

</body>
</html>
