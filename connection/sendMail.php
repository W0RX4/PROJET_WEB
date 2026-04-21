<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

if (!isset($_ENV['SMTP_HOST'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

function sendVerificationEmail(string $toEmail, string $toName, string $code): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? '';
        $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 465);
        $mail->CharSet    = 'UTF-8';

        $fromEmail = $_ENV['SMTP_FROM'] ?? $_ENV['SMTP_USER'] ?? '';
        $fromName  = $_ENV['SMTP_FROM_NAME'] ?? 'StageArchive';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Votre code de verification - StageArchive';

        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 32px; background: #F8FAFC;">
                <div style="background: #FFFFFF; border-radius: 16px; padding: 40px; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
                    <h1 style="background: linear-gradient(135deg, #2563EB, #8B5CF6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0 0 8px; font-size: 28px;">StageArchive</h1>
                    <p style="color: #64748B; margin: 0 0 28px;">Authentification a double facteur</p>

                    <p style="color: #0F172A; font-size: 16px; margin-bottom: 12px;">Bonjour ' . htmlspecialchars($toName) . ',</p>
                    <p style="color: #64748B; margin-bottom: 24px;">Voici votre code de verification pour vous connecter :</p>

                    <div style="background: linear-gradient(135deg, rgba(37,99,235,0.08), rgba(139,92,246,0.1)); border: 1px solid rgba(139,92,246,0.15); border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 24px;">
                        <div style="font-size: 38px; font-weight: 700; letter-spacing: 12px; color: #2563EB;">' . htmlspecialchars($code) . '</div>
                    </div>

                    <p style="color: #64748B; font-size: 14px; margin-bottom: 8px;">Ce code expirera dans <strong>10 minutes</strong>.</p>
                    <p style="color: #64748B; font-size: 14px;">Si vous n\'avez pas tente de vous connecter, ignorez ce message.</p>
                </div>
                <p style="text-align: center; color: #94A3B8; font-size: 12px; margin-top: 20px;">&copy; 2026 CY Tech - StageArchive</p>
            </div>
        ';

        $mail->AltBody = "Votre code de verification StageArchive : $code\n\nCe code expire dans 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

function generateVerificationCode(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
