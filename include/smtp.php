<?php

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
    private function isConfigured(): bool
    {
        return SMTP_HOST !== '#' && SMTP_USERNAME !== '#' && SMTP_PASSWORD !== '#';
    }

    private function configure(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->SMTPKeepAlive = true;
        $mail->isHTML(true);
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    }

    /**
     * Send a single email. Returns true on success, false on failure.
     * Failures are logged via error_log() so the caller never blocks on mail.
     */
    public function send_mail(string $email, string $message, string $subject): bool
    {
        if (!$this->isConfigured()) {
            error_log("[mailer] SMTP not configured (SMTP_HOST/USERNAME/PASSWORD are placeholders); skipping mail to {$email}");
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $this->configure($mail);
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->send();
            $mail->smtpClose();
            return true;
        } catch (Exception $e) {
            error_log("[mailer] send_mail to {$email} failed: " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
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
            error_log("[mailer] SMTP not configured; skipping mail to {$email} + WEB_EMAIL");
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("[mailer] send_to_both: invalid user email address '{$email}'; skipping user delivery");
            return $this->send_mail(WEB_EMAIL, $message, $subject);
        }

        $user_ok  = $this->send_mail($email,    $message, $subject);
        $admin_ok = $this->send_mail(WEB_EMAIL, $message, $subject);

        return $user_ok && $admin_ok;
    }
}
