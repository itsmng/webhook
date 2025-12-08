<?php

namespace GlpiPlugin\Webhook;

use Dropdown;
use Html;
use NotificationSetting;
use Plugin;
use Session;

class NotificationWebhookSetting extends NotificationSetting {

    public static function getTypeName($nb = 0) {
        return __('Webhook notifications configuration', 'webhook');
    }

    public static function getMode() {
        return 'webhook';
    }

    public function getEnableLabel() {
        return __('Enable webhook notifications', 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/notificationwebhooksetting.form.php';
    }

    protected function showFormConfig($options = []): bool {
        if (!Session::haveRight('config', UPDATE)) {
            Html::displayRightError();
            return false;
        }

        $enabled   = (bool) Config::getValue('notifications_webhook', 1);
        $timeout   = (int) Config::getValue('webhook_default_timeout', 10);
        $verifySsl = (bool) Config::getValue('webhook_verify_ssl', 1);

        echo "<form action='" . self::getFormURL() . "' method='post'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Webhook notification settings', 'webhook') . "</th></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td>" . $this->getEnableLabel() . "</td><td>";
        Dropdown::showYesNo('notifications_webhook', $enabled ? 1 : 0, -1, ['display' => true]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Default timeout (seconds)', 'webhook') . "</td><td>";
        echo Html::input('webhook_default_timeout', ['type' => 'number', 'min' => 1, 'max' => 3600, 'value' => $timeout]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Verify SSL certificates', 'webhook') . "</td><td>";
        Dropdown::showYesNo('webhook_verify_ssl', $verifySsl ? 1 : 0, -1, ['display' => true]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
        echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
        echo "</td></tr>";

        echo "</table>";
        Html::closeForm();
        return true;
    }
}

class_alias(NotificationWebhookSetting::class, 'PluginWebhookNotificationWebhookSetting');
