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

        $this->fields['entities_id'] = $this->fields['entities_id'] ?? Session::getActiveEntity();
        $this->fields['is_recursive'] = $this->fields['is_recursive'] ?? Session::getIsActiveEntityRecursive();

        if (function_exists('renderTwigForm')) {
            $headers = json_decode($this->fields['headers'] ?? '{}', true) ?: [];
            $rand = mt_rand();

            $form = [
                'action'  => $this->getFormURL(),
                'itemtype'=> self::class,
                'content' => [
                    $this->getTypeName(1) => [
                        'visible' => true,
                        'inputs'  => [
                            __('Name') => [
                                'type'  => 'text',
                                'name'  => 'name',
                                'value' => Html::entities_deep($this->fields['name'] ?? ''),
                                'col_md' => 6,
                                'col_lg' => 6,
                                'max'   => 255,
                            ],
                            __('Active') => [
                                'type'  => 'checkbox',
                                'name'  => 'is_active',
                                'value' => $this->fields['is_active'] ?? 1,
                            ],
                            __('URL') => [
                                'type'  => 'text',
                                'name'  => 'url',
                                'value' => Html::entities_deep($this->fields['url'] ?? ''),
                                'size'  => 80,
                            ],
                            __('HTTP method', 'webhook') => [
                                'type'   => 'select',
                                'name'   => 'http_method',
                                'values' => ['POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'GET' => 'GET'],
                                'value'  => $this->fields['http_method'] ?? 'POST',
                            ],
                            __('Timeout (s)', 'webhook') => [
                                'type'  => 'number',
                                'name'  => 'timeout',
                                'min'   => 1,
                                'max'   => 3600,
                                'value' => $this->fields['timeout'] ?? Config::getValue('webhook_default_timeout', 5),
                            ],
                            __('Verify SSL', 'webhook') => [
                                'type'  => 'checkbox',
                                'name'  => 'verify_ssl',
                                'value' => $this->fields['verify_ssl'] ?? Config::getValue('webhook_verify_ssl', 1),
                            ],
                            __('Comment') => [
                                'type'  => 'textarea',
                                'name'  => 'comment',
                                'value' => Html::entities_deep($this->fields['comment'] ?? ''),
                                'col_md' => 12,
                                'col_lg' => 12,
                            ],
                            [
                                'type'  => 'hidden',
                                'name'  => 'headers_json',
                                'value' => Html::entities_deep(json_encode($headers)),
                            ],
                        ],
                    ],
                ],
            ];

            renderTwigForm($form, $this->renderHeadersBlockTwig($headers, $rand), $this->fields);
            return true;
        }

        return $this->renderLegacyForm($options);
    }

    private function renderLegacyForm(array $options): bool {
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
        Dropdown::showFromArray('http_method', ['POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'GET' => 'GET'], [
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
        echo $this->renderHeadersBlockLegacy($headers, $rand);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Comment') . "</td><td colspan='3'>";
        echo Html::textarea(['name' => 'comment', 'value' => $this->fields['comment'] ?? '', 'cols' => 80, 'rows' => 3]);
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    private function renderHeadersBlockTwig(array $headers, int $rand): string {
        $tableId   = "webhook-headers-table-$rand";
        $indexVar  = "webhookHeaderIndex$rand";
        $addFn     = "webhookAddHeaderRow$rand";
        $removeFn  = "webhookRemoveHeaderRow$rand";

        $wrapperStart = "<div class='form-section mb-3' id='webhook-headers-container-$rand'><h2 class='form-section-header'>" . __('HTTP Headers', 'webhook') . "</h2><div class='form-section-content'>";
        $wrapperEnd   = "</div></div>";

        $htmlParts   = [];
        $htmlParts[] = $wrapperStart;
        $htmlParts[] = "<table class='table table-sm table-striped align-middle' id='$tableId'>";
        $htmlParts[] = "<thead class='table-light'><tr><th>" . __('Header Name', 'webhook') . "</th><th>" . __('Header Value', 'webhook') . "</th><th class='text-end'></th></tr></thead>";
        $htmlParts[] = "<tbody>";

        $i = 0;
        foreach ($headers as $key => $val) {
            $htmlParts[] = "<tr>"
                . "<td>" . Html::input("header_keys[$i]", ['value' => $key, 'size' => 30, 'class' => 'form-control form-control-sm']) . "</td>"
                . "<td>" . Html::input("header_values[$i]", ['value' => $val, 'size' => 50, 'class' => 'form-control form-control-sm']) . "</td>"
                . "<td class='text-end'><a href='#' class='btn btn-outline-danger btn-sm' onclick='return $removeFn(this);'><i class='fas fa-trash'></i></a></td>"
                . "</tr>";
            $i++;
        }

        $htmlParts[] = "</tbody>";
        $htmlParts[] = "</table>";
        $htmlParts[] = "<button class='btn btn-outline-secondary btn-sm mt-2 w-auto' style='max-width: 220px;' onclick=\"return $addFn('$tableId');\" type='button'><i class='fas fa-plus'></i> " . __('Add header', 'webhook') . "</button>";
        $htmlParts[] = $wrapperEnd;

        $script = <<<JS
var $indexVar = {$i};
function $addFn(tableId) {
    var tbody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="header_keys[' + $indexVar + ']" size="30" class="form-control form-control-sm"></td>' +
                   '<td><input type="text" name="header_values[' + $indexVar + ']" size="50" class="form-control form-control-sm"></td>' +
                   '<td class="text-end"><a href="#" class="btn btn-outline-danger btn-sm" onclick="return $removeFn(this);"><i class="fas fa-trash"></i></a></td>';
    tbody.appendChild(tr);
    $indexVar++;
    return false;
}
function $removeFn(el) {
    var tr = el.closest('tr');
    if (tr && tr.parentNode) {
        tr.parentNode.removeChild(tr);
    }
    return false;
}
JS;

        $htmlParts[] = Html::scriptBlock($script);

        return implode('', $htmlParts);
    }

    private function renderHeadersBlockLegacy(array $headers, int $rand): string {
        $tableId   = "webhook-headers-table-$rand";
        $indexVar  = "webhookHeaderIndex$rand";
        $addFn     = "webhookAddHeaderRow$rand";
        $removeFn  = "webhookRemoveHeaderRow$rand";

        $htmlParts   = [];
        $htmlParts[] = "<div id='webhook-headers-container-$rand'>";
        $htmlParts[] = "<table class='tab_cadre_fixe' id='$tableId'>";
        $htmlParts[] = "<thead class='tab_bg_2'><tr><th>" . __('Header Name', 'webhook') . "</th><th>" . __('Header Value', 'webhook') . "</th><th></th></tr></thead>";
        $htmlParts[] = "<tbody>";

        $i = 0;
        foreach ($headers as $key => $val) {
            $htmlParts[] = "<tr class='tab_bg_1'>"
                . "<td>" . Html::input("header_keys[$i]", ['value' => $key, 'size' => 30]) . "</td>"
                . "<td>" . Html::input("header_values[$i]", ['value' => $val, 'size' => 50]) . "</td>"
                . "<td><a href='#' class='vsubmit' onclick='return $removeFn(this);'><i class='fas fa-trash'></i></a></td>"
                . "</tr>";
            $i++;
        }

        $htmlParts[] = "</tbody>";
        $htmlParts[] = "</table>";
        $htmlParts[] = "<a href='#' class='vsubmit' onclick=\"return $addFn('$tableId');\"><i class='fas fa-plus'></i> " . __('Add header', 'webhook') . "</a>";
        $htmlParts[] = "</div>";

        $script = <<<JS
var $indexVar = {$i};
function $addFn(tableId) {
    var tbody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    var tr = document.createElement('tr');
    tr.className = 'tab_bg_1';
    tr.innerHTML = '<td><input type="text" name="header_keys[' + $indexVar + ']" size="30"></td>' +
                   '<td><input type="text" name="header_values[' + $indexVar + ']" size="50"></td>' +
                   '<td><a href="#" class="vsubmit" onclick="return $removeFn(this);"><i class="fas fa-trash"></i></a></td>';
    tbody.appendChild(tr);
    $indexVar++;
    return false;
}
function $removeFn(el) {
    var tr = el.closest('tr');
    if (tr && tr.parentNode) {
        tr.parentNode.removeChild(tr);
    }
    return false;
}
JS;

        $htmlParts[] = Html::scriptBlock($script);

        return implode('', $htmlParts);
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
