<?php

declare(strict_types=1);

/**
 * Plain-text email transport (PHP mail()).
 *
 * - Disabled by default: mail_enabled must be true in config/app.php.
 * - NEVER throws: every failure is swallowed and error_logged so a
 *   notification problem can never break the business flow that
 *   triggered it.
 * - No secrets are stored here; the from address comes from config.
 */
class Mailer
{
    public static function enabled(): bool
    {
        return (bool) app_config('mail_enabled', false);
    }

    /**
     * Send one plain-text message.
     *
     * @return bool true only when the message was handed to mail()
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        try {
            if (!self::enabled()) {
                return false;
            }

            $to = trim($to);

            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            // CR/LF injection guard for both header-carrying fields.
            $subject = trim(str_replace(["\r", "\n"], ' ', $subject));

            $fromAddress = trim(
                (string) app_config('mail_from_address', 'no-reply@localhost')
            );

            $fromName = trim(
                (string) app_config('mail_from_name', 'StudentHub')
            );

            $fromName = str_replace(["\r", "\n", '"'], '', $fromName);

            if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                $fromAddress = 'no-reply@localhost';
            }

            $headers  = 'From: ' . $fromName . ' <' . $fromAddress . ">\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "X-Mailer: StudentHub";

            $ok = @mail(
                $to,
                $subject,
                wordwrap(str_replace(["\r\n", "\r"], "\n", $body), 72),
                $headers
            );

            if (!$ok) {
                error_log('Mailer: mail() failed for recipient ' . $to);
            }

            return $ok;
        } catch (Throwable $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }
}
