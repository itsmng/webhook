<?php

namespace GlpiPlugin\Webhook;

use CommonGLPI;
use NotificationEventAbstract;
use NotificationEventInterface;
use NotificationTarget;
use NotificationTemplate;
use Session;
use Toolbox;
use GlpiPlugin\Webhook\Template;

class NotificationEventWebhook extends NotificationEventAbstract implements NotificationEventInterface {
    use Permissions;

    public static function getTargetFieldName() {
        return 'email';
    }

    public static function getTargetField(&$data) {
        return self::getTargetFieldName();
    }

    public static function canCron() {
        return true;
    }

    public static function getAdminData() {
        return false;
    }

    public static function getEntityAdminsData($entity) {
        return false;
    }

    public static function raise(
        $event,
        CommonGLPI $item,
        array $options,
        $label,
        array $data,
        NotificationTarget $notificationtarget,
        NotificationTemplate $template,
        $notify_me,
        $emitter = null
    ) {
        if (!Config::getValue('notifications_webhook', 1)) {
            return;
        }

        $entity = $notificationtarget->getEntity();
        $rules = Notification::getWebhookNotifications($event, $item->getType(), $entity);

        foreach ($rules as $rule) {
            $webhook = new Webhook();
            if (!$webhook->getFromDB($rule['plugin_webhook_webhooks_id']) || !$webhook->fields['is_active']) {
                continue;
            }

            // Resolve template translation
            $translation = new TemplateTranslation();
            $lang = $options['language'] ?? ($_SESSION['glpilanguage'] ?? '');
            if (!$translation->getFromDBByCrit([
                'plugin_webhook_templates_id' => $rule['plugin_webhook_templates_id'],
                'language' => $lang
            ])) {
                $translation->getFromDBByCrit([
                    'plugin_webhook_templates_id' => $rule['plugin_webhook_templates_id'],
                    'language' => ''
                ]);
            }
            $payloadTemplate = $translation->fields['payload_template'] ?? Template::getDefaultPayloadTemplate();

            $tags = [];
            if (method_exists($notificationtarget, 'getForTemplate')) {
                $tags = $notificationtarget->getForTemplate($event, $options);
            }
            $payload = TemplateTranslation::processPayloadTemplate($payloadTemplate, $tags);

            $config = json_encode([
                'timeout'    => (int)$webhook->fields['timeout'],
                'verify_ssl' => (bool)$webhook->fields['verify_ssl'],
            ]);

            $queueRow = [
                'recipient' => $webhook->fields['url'],
                'sender' => $webhook->fields['http_method'],
                'sendername' => $config,
                'headers' => $webhook->fields['headers'],
                'body_text' => $payload,
                'mode' => 'webhook',
            ];

            (new NotificationWebhook())->sendNotification($queueRow);
        }
    }

    public static function send(array $data) {
        $sent = 0;
        foreach ($data as $row) {
            $sent += (new NotificationWebhook())->sendNotification($row);
        }
        return $sent;
    }
}
