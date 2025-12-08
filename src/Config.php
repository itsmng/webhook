<?php

namespace GlpiPlugin\Webhook;

use CommonDBTM;
use Dropdown;
use Html;
use Plugin;
use Session;
use Toolbox;

class Config extends CommonDBTM {
    use Permissions;

    public static function getTable($classname = null) {
        return 'glpi_plugin_webhook_configs';
    }

    public static function getTypeName($nb = 0) {
        return _n('Webhook configuration', 'Webhook configurations', $nb, 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/config.form.php';
    }

    public static function canView() {
        return self::canConfig();
    }

    public static function canCreate() {
        return self::canConfig();
    }

    public static function canUpdate() {
        return self::canConfig();
    }

    public static function install() {
        global $DB;

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $query = <<<SQL
CREATE TABLE `$table` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `value` text COLLATE utf8_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unicity` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;
            $DB->queryOrDie($query, $DB->error());
        }

        $defaults = [
            'notifications_webhook'    => '1',
            'webhook_default_timeout'  => '5',
            'webhook_verify_ssl'       => '1',
        ];

        foreach ($defaults as $key => $value) {
            if (!countElementsInTable($table, ['name' => $key])) {
                $DB->insert($table, [
                    'name'  => $key,
                    'value' => $value,
                ]);
            }
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

    public static function getValue(string $name, $default = null) {
        global $DB;
        $res = $DB->request([
            'SELECT' => ['value'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['name' => $name]
        ]);
        foreach ($res as $row) {
            return $row['value'];
        }
        return $default;
    }

    public static function setValues(array $values): bool {
        global $DB;
        foreach ($values as $key => $value) {
            if (countElementsInTable(self::getTable(), ['name' => $key])) {
                $DB->update(self::getTable(), ['value' => $value], ['name' => $key]);
            } else {
                $DB->insert(self::getTable(), ['name' => $key, 'value' => $value]);
            }
        }
        return true;
    }

    public function showConfigForm() {
        if (!self::canConfig()) {
            return false;
        }

        $timeout   = (int) self::getValue('webhook_default_timeout', 5);
        $verifySsl = (bool) self::getValue('webhook_verify_ssl', 1);
        $enabled   = (bool) self::getValue('notifications_webhook', 1);

        if (function_exists('renderTwigForm')) {
            $form = [
                'action'  => self::getFormURL(),
                'content' => [
                    __('Webhook settings', 'webhook') => [
                        'visible' => true,
                        'inputs'  => [
                            __('Enable webhook notifications', 'webhook') => [
                                'type'  => 'checkbox',
                                'name'  => 'notifications_webhook',
                                'value' => $enabled ? 1 : 0,
                            ],
                            __('Default timeout (seconds)', 'webhook') => [
                                'type'  => 'number',
                                'name'  => 'webhook_default_timeout',
                                'value' => $timeout,
                                'min'   => 1,
                                'max'   => 3600,
                            ],
                            __('Verify SSL certificates', 'webhook') => [
                                'type'  => 'checkbox',
                                'name'  => 'webhook_verify_ssl',
                                'value' => $verifySsl ? 1 : 0,
                            ],
                        ],
                    ],
                ],
                'buttons' => [
                    [
                        'class' => 'btn btn-primary',
                        'name'  => 'update',
                        'value' => _sx('button', 'Save'),
                        'type'  => 'submit',
                    ],
                ],
            ];

            renderTwigForm($form, '', ['id' => $this->fields['id'] ?? 0]);
            return true;
        }

        echo "<div class='spaced'>";
        echo "<div class='center'>";
        echo "<form method='post' action='" . self::getFormURL() . "'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Webhook settings', 'webhook') . "</th></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Enable webhook notifications', 'webhook') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('notifications_webhook', $enabled ? 1 : 0);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Default timeout (seconds)', 'webhook') . "</td>";
        echo "<td>" . Html::input('webhook_default_timeout', ['value' => $timeout, 'type' => 'number', 'min' => 1, 'max' => 3600]) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Verify SSL certificates', 'webhook') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('webhook_verify_ssl', $verifySsl ? 1 : 0);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='2' class='center'>";
        echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        Html::closeForm();
        echo "</div></div>";

        return true;
    }
}
