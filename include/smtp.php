<?php

// PHPMailer lives under include/vendor. Loading the autoloader HERE rather
// than relying on the caller is what makes this file self-sufficient: every
// endpoint that only did `require 'smtp.php'` (profile-email-request.php,
// profile-email-verify.php, profile.php, transfer-verify-pin.php and the
// whole api/auth bootstrap) previously constructed PHPMailer with no
// autoloader registered. The resulting "class not found" Error was caught by
// the Throwable handler in send_mail(), logged, and turned into `false` —
// which every one of those callers ignored. The user got a success toast and
// no email.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * MESSAGE & EMAIL CONFIGURATION FOR SCRIPT
 *
 * Reads SMTP credentials from constants defined in include/config.php
 * (SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD, SMTP_PORT, SMTP_SECURE,
 * SMTP_FROM_EMAIL, SMTP_FROM_NAME). Override via environment variables.
 */
class message
{
    /** @var string Reason the last send failed, for callers that surface errors. */
    private $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * A missing constant must read as "not configured", not as a fatal. On
     * PHP 7.4 a bare undefined constant raises a warning and evaluates to its
     * own name, which would sail past a `!== '#'` test and hand PHPMailer
     * garbage credentials.
     */
    private function isConfigured(): bool
    {
        foreach (array('SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD') as $constant) {
            if (!defined($constant)) {
                return false;
            }
            $value = (string)constant($constant);
            if ($value === '' || $value === '#') {
                return false;
            }
        }
        return true;
    }

    private function configure(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;
        // Each send builds its own instance, so there is no second message to
        // keep the socket open for. Leaving keep-alive on meant a send() that
        // threw skipped smtpClose() and leaked the connection for the rest of
        // the request.
        $mail->SMTPKeepAlive = false;
        $mail->isHTML(true);

        $fromEmail = defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL !== '#' ? SMTP_FROM_EMAIL : SMTP_USERNAME;
        $fromName  = defined('SMTP_FROM_NAME') && SMTP_FROM_NAME !== '#' ? SMTP_FROM_NAME : 'Notification';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);

        // Plain-text alternative. Without it every message is single-part
        // HTML, which is a strong spam signal and renders as raw markup in
        // text-only clients.
        $mail->AltBody = '';
    }

    /**
     * Send a single email. Returns true on success, false on failure.
     * Failures are logged via error_log() so the caller never blocks on mail.
     */
    public function send_mail(string $email, string $message, string $subject): bool
    {
        $this->lastError = '';

        if (!$this->isConfigured()) {
            $this->lastError = 'SMTP is not configured (SMTP_HOST/USERNAME/PASSWORD are unset or placeholders).';
            error_log("[mailer] {$this->lastError} Skipping mail to {$email}");
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Invalid recipient address '{$email}'.";
            error_log("[mailer] {$this->lastError}");
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $this->configure($mail);
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = trim(html_entity_decode(strip_tags($message), ENT_QUOTES, 'UTF-8'));
            $mail->send();
            $mail->smtpClose();
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("[mailer] send_mail to {$email} failed: " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log("[mailer] send_mail to {$email} unexpected: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the same email to the user AND the admin (WEB_EMAIL).
     * Uses two independent PHPMailer instances so SMTP recipient lists
     * can never bleed between the two deliveries.
     */
    public function send_to_both(string $email, string $message, string $subject): bool
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'SMTP is not configured.';
            error_log("[mailer] SMTP not configured; skipping mail to {$email} + WEB_EMAIL");
            return false;
        }

        $adminEmail = defined('WEB_EMAIL') ? (string)WEB_EMAIL : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("[mailer] send_to_both: invalid user email address '{$email}'; skipping user delivery");
            return $adminEmail !== '' ? $this->send_mail($adminEmail, $message, $subject) : false;
        }

        // The customer copy is the one that matters. A bounced operator BCC
        // must not make the caller believe the customer was never told —
        // several callers gate user-visible state on this return value.
        $user_ok = $this->send_mail($email, $message, $subject);

        if ($adminEmail !== '' && strcasecmp($adminEmail, $email) !== 0) {
            $this->send_mail($adminEmail, $message, $subject);
        }

        return $user_ok;
    }
}
