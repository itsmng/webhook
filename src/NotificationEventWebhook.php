<?php

namespace GlpiPlugin\Webhook;

use CommonGLPI;
use NotificationEventAbstract;
use NotificationEventInterface;
use NotificationTarget;
use NotificationTemplate;

class NotificationEventWebhook extends NotificationEventAbstract implements NotificationEventInterface {
    use Permissions;

    public static function getTargetFieldName() {
        return 'users_id';
    }

    public static function getTargetField(&$data) {
        $field = self::getTargetFieldName();
        $data[$field] = $data[$field] ?? null;

        return $field;
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
        if (
            $label !== ''
            || !Config::getValue('notifications_webhook', 1)
        ) {
            return;
        }

        $processed = [];
        if (isset($options['processed'])) {
            $processed = &$options['processed'];
            unset($options['processed']);
        }

        $notificationId = (int)($data['id'] ?? 0);
        if ($notificationId <= 0) {
            return;
        }

        foreach (getAllDataFromTable('glpi_notificationtargets', ['notifications_id' => $notificationId]) as $target) {
            $notificationtarget->addForTarget($target, $options);
        }

        $eligibleWebhookOptions = [];
        foreach ($notificationtarget->getTargets() as $recipient) {
            $userId = (int)($recipient['users_id'] ?? 0);
            if (
                $userId <= 0
                || !$notificationtarget->validateSendTo($event, $recipient, $notify_me, $emitter)
            ) {
                continue;
            }

            foreach (UserWebhook::getWebhooksForUser($userId) as $webhookId) {
                $webhookId = (int)$webhookId;
                $recipientOptions = $recipient['additionnaloption'] ?? [];
                if (!isset($eligibleWebhookOptions[$webhookId])) {
                    $eligibleWebhookOptions[$webhookId] = $recipientOptions;
                    continue;
                }

                // A webhook shared by multiple eligible recipients uses the
                // highest visibility granted to one of its assigned users.
                if (!empty($recipientOptions['show_private'])) {
                    $eligibleWebhookOptions[$webhookId]['show_private'] = 1;
                }
            }
        }
        if (!$eligibleWebhookOptions) {
            return;
        }

        $entity = $notificationtarget->getEntity();
        foreach (Notification::getWebhookNotifications($event, $item->getType(), $entity) as $rule) {
            $ruleId = (int)$rule['id'];
            $webhookId = (int)$rule['plugin_webhook_webhooks_id'];
            if (isset($processed['rules'][$ruleId]) || !isset($eligibleWebhookOptions[$webhookId])) {
                continue;
            }

            $webhook = new Webhook();
            if (!$webhook->getFromDBByCrit([
                'id' => $webhookId,
                'is_active' => 1,
            ] + getEntitiesRestrictCriteria(Webhook::getTable(), 'entities_id', $entity, true))) {
                continue;
            }

            // Resolve template translation
            $translation = new TemplateTranslation();
            $lang = $options['language'] ?? ($_SESSION['glpilanguage'] ?? '');
            if (!$translation->getFromDBByCrit([
                'plugin_webhook_templates_id' => $rule['plugin_webhook_templates_id'],
                'language' => $lang
            ]) && $lang !== '') {
                $translation->getFromDBByCrit([
                    'plugin_webhook_templates_id' => $rule['plugin_webhook_templates_id'],
                    'language' => ''
                ]);
            }
            $renderOptions = $options;
            $renderOptions['additionnaloption'] = array_merge(
                $renderOptions['additionnaloption'] ?? [],
                $eligibleWebhookOptions[$webhookId],
                ['usertype' => NotificationTarget::ANONYMOUS_USER]
            );
            $payload = TemplateTranslation::processPayloadTemplate(
                $translation->fields['payload_template'] ?? Template::getDefaultPayloadTemplate(),
                $notificationtarget->getForTemplate($event, $renderOptions)
            );

            (new NotificationWebhook())->sendNotification([
                'recipient' => $webhook->fields['url'],
                'sender' => $webhook->fields['http_method'],
                'sendername' => json_encode([
                    'timeout' => (int)$webhook->fields['timeout'],
                    'verify_ssl' => (bool)$webhook->fields['verify_ssl'],
                ]),
                'headers' => $webhook->fields['headers'],
                'body_text' => $payload,
                'mode' => 'webhook',
            ]);
            $processed['rules'][$ruleId] = true;
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
