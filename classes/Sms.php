<?php

declare(strict_types=1);

/**
 * SMS transport through a generic HTTP gateway.
 *
 * - Disabled by default: sms_enabled must be true AND sms_api_url must
 *   be set in config/app.php.
 * - NEVER throws: failures are swallowed and error_logged so a
 *   notification problem can never break the business flow.
 * - The API key is read from config (override via environment in
 *   production) — no secrets are stored in this file.
 */
class Sms
{
    public static function enabled(): bool
    {
        return (bool) app_config('sms_enabled', false);
    }

    /**
     * Send one plain-text SMS.
     *
     * @return bool true only when the gateway accepted the request
     */
    public static function send(string $phone, string $message): bool
    {
        try {
            if (!self::enabled()) {
                return false;
            }

            $phone = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';

            if ($phone === '' || strlen($phone) < 6) {
                return false;
            }

            $url = trim((string) app_config('sms_api_url', ''));
            $apiKey = (string) app_config('sms_api_key', '');

            if ($url === '') {
                error_log(
                    'Sms: sms_enabled is true but sms_api_url is not configured.'
                );
                return false;
            }

            $message = str_replace(["\r", "\n"], ' ', trim($message));

            if ($message === '') {
                return false;
            }

            $payload = http_build_query([
                'to'      => $phone,
                'message' => $message,
                'key'     => $apiKey,
            ]);

            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 5,
                ],
            ]);

            $result = @file_get_contents($url, false, $context);

            if ($result === false) {
                error_log('Sms: gateway request failed for ' . $phone);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            error_log('Sms error: ' . $e->getMessage());
            return false;
        }
    }
}
