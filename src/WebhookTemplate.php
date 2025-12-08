<?php

namespace GlpiPlugin\Webhook;

use CommonDBTM;
use Dropdown;
use Html;
use Plugin;

class WebhookTemplate extends CommonDBTM {
    use Permissions;

    public static function getTable($classname = null) {
        return 'glpi_plugin_webhook_webhook_templates';
    }

    public static function getTypeName($nb = 0) {
        return _n('Webhook template binding', 'Webhook template bindings', $nb, 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/webhooktemplate.form.php';
    }

    public static function canView() { return self::canUse(); }
    public static function canCreate() { return self::canConfig(); }
    public static function canUpdate() { return self::canConfig(); }
    public static function canDelete() { return self::canConfig(); }

    public static function install() {
        global $DB;
        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $query = <<<SQL
CREATE TABLE `$table` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `plugin_webhook_webhooks_id` int(11) NOT NULL,
    `itemtype` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `plugin_webhook_templates_id` int(11) NOT NULL,
    `notifications_id` int(11) DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT '1',
    `date_creation` timestamp NULL DEFAULT NULL,
    `date_mod` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unicity` (`plugin_webhook_webhooks_id`,`itemtype`,`notifications_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;
            $DB->queryOrDie($query, $DB->error());
        }
        return true;
    }

    public static function uninstall() {
        global $DB;
        $table = self::getTable();
        if ($DB->tableExists($table)) {
            $DB->queryOrDie("DROP TABLE `$table`", $DB->error());
        }
        return true;
    }

    public function showForm($ID, $options = []) {
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Webhook', 'webhook') . "</td><td>";
        Dropdown::show('PluginWebhookWebhook', [
            'name' => 'plugin_webhook_webhooks_id',
            'value' => $this->fields['plugin_webhook_webhooks_id'] ?? 0,
            'comments' => false
        ]);
        echo "</td>";
        echo "<td>" . __('Item type') . "</td><td>";
        Dropdown::showItemTypes('itemtype', $CFG_GLPI['notificationtemplates_types'], ['value' => $this->fields['itemtype'] ?? 'Ticket']);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Template', 'webhook') . "</td><td>";
        Dropdown::show('PluginWebhookTemplate', [
            'name' => 'plugin_webhook_templates_id',
            'value' => $this->fields['plugin_webhook_templates_id'] ?? 0,
            'comments' => false
        ]);
        echo "</td>";
        echo "<td>" . __('Notification (optional)', 'webhook') . "</td><td>";
        Dropdown::show('Notification', [
            'name'   => 'notifications_id',
            'value'  => $this->fields['notifications_id'] ?? 0,
            'comments' => false,
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Active') . "</td><td>";
        Dropdown::showYesNo('is_active', $this->fields['is_active'] ?? 1);
        echo "</td><td></td><td></td></tr>";

        $this->showFormButtons($options);
        return true;
    }
}

class_alias(WebhookTemplate::class, 'PluginWebhookWebhookTemplate');
