<?php

namespace GlpiPlugin\Webhook;

use CommonDBTM;
use Dropdown;
use Html;
use Plugin;
use Session;
use Toolbox;

class Webhook extends CommonDBTM {
    use Permissions;

    public static $rightname = 'plugin_webhook_use';
    public $dohistory = true;

    public static function getTable($classname = null) {
        return 'glpi_plugin_webhook_webhooks';
    }

    public static function getTypeName($nb = 0) {
        return _n('Webhook', 'Webhooks', $nb, 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/webhook.form.php';
    }

    public static function getSearchURL($full = true) {
        return Plugin::getWebDir('webhook') . '/front/webhook.php';
    }

    public static function canView() {
        return self::canUse();
    }

    public static function canCreate() {
        return self::canConfig();
    }

    public static function canUpdate() {
        return self::canConfig();
    }

    public static function canDelete() {
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
    `url` text COLLATE utf8_unicode_ci NOT NULL,
    `http_method` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'POST',
    `headers` text COLLATE utf8_unicode_ci DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT '1',
    `timeout` int(11) NOT NULL DEFAULT '10',
    `verify_ssl` tinyint(1) NOT NULL DEFAULT '1',
    `entities_id` int(11) NOT NULL DEFAULT '0',
    `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
    `comment` text COLLATE utf8_unicode_ci DEFAULT NULL,
    `date_creation` timestamp NULL DEFAULT NULL,
    `date_mod` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `entities_id` (`entities_id`),
    KEY `is_active` (`is_active`)
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

    public function prepareInputForAdd($input) {
        return $this->validateInput($input);
    }

    public function prepareInputForUpdate($input) {
        return $this->validateInput($input);
    }

    private function validateInput($input) {
        if (isset($input['url'])) {
            $url = filter_var($input['url'], FILTER_VALIDATE_URL);
            if (!$url || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                Session::addMessageAfterRedirect(__('Invalid URL', 'webhook'), false, ERROR);
                return false;
            }
            $input['url'] = $url;
        }
        $allowedMethods = ['POST', 'PUT', 'PATCH', 'GET'];
        if (isset($input['http_method']) && !in_array(strtoupper($input['http_method']), $allowedMethods, true)) {
            $input['http_method'] = 'POST';
        }
        $input['http_method'] = strtoupper($input['http_method'] ?? 'POST');


        if (isset($input['header_keys']) && isset($input['header_values'])) {
            $headers = [];
            foreach ($input['header_keys'] as $i => $key) {
                $key = trim($key);
                $val = trim($input['header_values'][$i] ?? '');
                if ($key !== '') {
                    $headers[$key] = $val;
                }
            }
            $input['headers'] = json_encode($headers);
            unset($input['header_keys'], $input['header_values']);
        } else {
            $input['headers'] = self::normalizeHeaders($input['headers'] ?? $input['headers_json'] ?? null);
        }

        $input['timeout'] = (int)($input['timeout'] ?? Config::getValue('webhook_default_timeout', 5));
        $input['verify_ssl'] = isset($input['verify_ssl']) ? (int)$input['verify_ssl'] : (int)Config::getValue('webhook_verify_ssl', 1);
        return $input;
    }

    private static function normalizeHeaders($headers) {
        if (is_array($headers)) {
            return json_encode($headers);
        }
        json_decode($headers);
        if (is_string($headers) && json_last_error() === JSON_ERROR_NONE) {
            return $headers;
        }
        return json_encode([]);
    }

    public function showForm($ID, $options = []) {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name') . "</td>";
        echo "<td>";
        echo Html::input('name', ['value' => $this->fields['name'] ?? '', 'size' => 50]);
        echo "</td>";
        echo "<td>" . __('Active') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('is_active', $this->fields['is_active'] ?? 1);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('URL') . "</td><td colspan='3'>";
        echo Html::input('url', ['value' => $this->fields['url'] ?? '', 'size' => 80]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('HTTP method', 'webhook') . "</td><td>";
        Dropdown::showFromArray('http_method', ['POST'=>'POST','PUT'=>'PUT','PATCH'=>'PATCH','GET'=>'GET'], [
            'value' => $this->fields['http_method'] ?? 'POST'
        ]);
        echo "</td>";
        echo "<td>" . __('Timeout (s)', 'webhook') . "</td><td>";
        echo Html::input('timeout', ['type' => 'number', 'min' => 1, 'max' => 3600, 'value' => $this->fields['timeout'] ?? 5]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Verify SSL', 'webhook') . "</td><td>";
        Dropdown::showYesNo('verify_ssl', $this->fields['verify_ssl'] ?? 1);
        echo "</td>";
        echo "<td></td><td></td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('HTTP Headers', 'webhook') . "</td><td colspan='3'>";
        $headers = json_decode($this->fields['headers'] ?? '{}', true) ?: [];
        $rand = mt_rand();
        echo "<div id='webhook-headers-container-$rand'>";
        echo "<table class='tab_cadre_fixe' id='webhook-headers-table-$rand'>";
        echo "<thead><tr class='tab_bg_2'><th>" . __('Header Name', 'webhook') . "</th><th>" . __('Header Value', 'webhook') . "</th><th></th></tr></thead>";
        echo "<tbody>";
        if (!empty($headers)) {
            $i = 0;
            foreach ($headers as $key => $val) {
                echo "<tr class='tab_bg_1'>";
                echo "<td>" . Html::input("header_keys[$i]", ['value' => $key, 'size' => 30]) . "</td>";
                echo "<td>" . Html::input("header_values[$i]", ['value' => $val, 'size' => 50]) . "</td>";
                echo "<td><a href='#' class='webhook-remove-header' onclick='return webhookRemoveHeaderRow(this);'><i class='fas fa-trash'></i></a></td>";
                echo "</tr>";
                $i++;
            }
        }
        echo "</tbody>";
        echo "</table>";
        echo "<a href='#' class='vsubmit' onclick='return webhookAddHeaderRow(\"webhook-headers-table-$rand\");'><i class='fas fa-plus'></i> " . __('Add header', 'webhook') . "</a>";
        echo "</div>";
        echo Html::scriptBlock("
            var webhookHeaderIndex = " . count($headers) . ";
            function webhookAddHeaderRow(tableId) {
                var tbody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
                var tr = document.createElement('tr');
                tr.className = 'tab_bg_1';
                tr.innerHTML = '<td><input type=\"text\" name=\"header_keys[' + webhookHeaderIndex + ']\" size=\"30\"></td>' +
                               '<td><input type=\"text\" name=\"header_values[' + webhookHeaderIndex + ']\" size=\"50\"></td>' +
                               '<td><a href=\"#\" onclick=\"return webhookRemoveHeaderRow(this);\"><i class=\"fas fa-trash\"></i></a></td>';
                tbody.appendChild(tr);
                webhookHeaderIndex++;
                return false;
            }
            function webhookRemoveHeaderRow(el) {
                var tr = el.closest('tr');
                tr.parentNode.removeChild(tr);
                return false;
            }
        ");
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Comment') . "</td><td colspan='3'>";
        echo Html::textarea(['name' => 'comment', 'value' => $this->fields['comment'] ?? '', 'cols' => 80, 'rows' => 3]);
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    public function rawSearchOptions() {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => __('Characteristics')
        ];

        $tab[] = [
            'id'            => '1',
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
            'autocomplete'  => true,
        ];

        $tab[] = [
            'id'       => '2',
            'table'    => $this->getTable(),
            'field'    => 'url',
            'name'     => __('URL'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => '3',
            'table'    => $this->getTable(),
            'field'    => 'http_method',
            'name'     => __('HTTP method', 'webhook'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => '4',
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => '5',
            'table'    => $this->getTable(),
            'field'    => 'timeout',
            'name'     => __('Timeout (s)', 'webhook'),
            'datatype' => 'number',
        ];

        $tab[] = [
            'id'       => '6',
            'table'    => $this->getTable(),
            'field'    => 'verify_ssl',
            'name'     => __('Verify SSL', 'webhook'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => '16',
            'table'    => $this->getTable(),
            'field'    => 'comment',
            'name'     => __('Comments'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'            => '19',
            'table'         => $this->getTable(),
            'field'         => 'date_mod',
            'name'          => __('Last update'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => '121',
            'table'         => $this->getTable(),
            'field'         => 'date_creation',
            'name'          => __('Creation date'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        return $tab;
    }
}

class_alias(Webhook::class, 'PluginWebhookWebhook');
