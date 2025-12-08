<?php

namespace GlpiPlugin\Webhook;

use NotificationInterface;
use Session;

class NotificationWebhook implements NotificationInterface {
    use Permissions;

    public static function getFormURL($full = false) {
        return \Plugin::getWebDir('webhook') . '/front/webhook.form.php';
    }

    public static function getSearchURL($full = true) {
        return \Plugin::getWebDir('webhook') . '/front/webhook.php';
    }

    public function sendNotification($options = []) {
        $url         = $options['recipient'] ?? '';
        $method      = $options['sender'] ?? 'POST';
        $headers     = $options['headers'] ?? '';
        $configJson  = $options['sendername'] ?? '';
        $payload     = $options['body_text'] ?? '';

        $config = [];
        if (is_string($configJson) && !empty($configJson)) {
            $decoded = json_decode($configJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $config = $decoded;
            }
        }
        $timeout   = isset($config['timeout']) ? (int)$config['timeout'] : (int)Config::getValue('webhook_default_timeout', 5);
        $verifySsl = array_key_exists('verify_ssl', $config) ? (bool)$config['verify_ssl'] : (bool)Config::getValue('webhook_verify_ssl', 1);

        $headersArr = [];
        if (is_string($headers) && !empty($headers)) {
            $decoded = json_decode($headers, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $headersArr = $decoded;
            } else {
                $legacy = @unserialize($headers);
                if (is_array($legacy)) {
                    $headersArr = $legacy;
                }
            }
        } else if (is_array($headers)) {
            $headersArr = $headers;
        }

        return self::sendWebhook($url, $payload, $method, $headersArr, $timeout, $verifySsl);
    }

    public static function check($value, $options = []) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function testNotification() {
        $webhook = new Webhook();
        if (!$webhook->getFromDBByCrit(['is_active' => 1])) {
            Session::addMessageAfterRedirect(__('No active webhook found to test', 'webhook'), false, WARNING);
            return false;
        }
        $payload = json_encode(['message' => 'Webhook test']);
        return self::sendWebhook(
            $webhook->fields['url'],
            $payload,
            $webhook->fields['http_method'] ?? 'POST',
            json_decode($webhook->fields['headers'] ?? '[]', true) ?: [],
            (int)$webhook->fields['timeout'],
            (bool)$webhook->fields['verify_ssl']
        );
    }

    public static function sendWebhook(string $url, string $payload, string $method = 'POST', array $headers = [], int $timeout = 10, bool $verifySsl = true) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 0;
        }

        $curl = curl_init();
        $httpHeaders = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $httpHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER     => $httpHeaders,
        ]);

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($error) {
            Session::addMessageAfterRedirect(sprintf(__('Webhook error: %s', 'webhook'), $error), false, ERROR);
            return 0;
        }
        if ($status >= 400) {
            Session::addMessageAfterRedirect(sprintf(__('Webhook HTTP error %s', 'webhook'), $status), false, ERROR);
            return 0;
        }
        return 1;
    }
}
