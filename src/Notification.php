<?php

namespace GlpiPlugin\Webhook;

use Ajax;
use CommonDBTM;
use Dropdown;
use Html;
use NotificationEvent;
use Plugin;
use Entity;
use Session;

class Notification extends CommonDBTM {
    use Permissions;

    public static $rightname = 'plugin_webhook_config';
    public $dohistory = true;

    public static function getTable($classname = null) {
        return 'glpi_plugin_webhook_notifications';
    }

    public static function getTypeName($nb = 0) {
        return _n('Webhook notification rule', 'Webhook notification rules', $nb, 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/notification.form.php';
    }

    public static function getSearchURL($full = true) {
        return Plugin::getWebDir('webhook') . '/front/notification.php';
    }

    public static function canView() { return self::canNotif() || self::canConfig(); }
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
    `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `plugin_webhook_webhooks_id` int(11) NOT NULL,
    `itemtype` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `event` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `entities_id` int(11) NOT NULL DEFAULT '0',
    `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
    `is_active` tinyint(1) NOT NULL DEFAULT '1',
    `plugin_webhook_templates_id` int(11) DEFAULT NULL,
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

    public static function getWebhookNotifications($event, $itemtype, $entities_id) {
        global $DB;
        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'event'    => $event,
                'itemtype' => $itemtype,
                'is_active' => 1
            ] + getEntitiesRestrictCriteria(self::getTable(), 'entities_id', $entities_id, true)
        ]);
        return iterator_to_array($iterator);
    }

    /**
     * Display the event name instead of raw event key in search results
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = []) {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'event':
                if (isset($values['itemtype']) && !empty($values['itemtype'])) {
                    return NotificationEvent::getEventName($values['itemtype'], $values[$field]);
                }
                break;
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public function showForm($ID, $options = []) {
        global $CFG_GLPI;

        $this->initForm($ID, $options);

        $this->fields['entities_id'] = $this->fields['entities_id'] ?? Session::getActiveEntity();
        $this->fields['is_recursive'] = $this->fields['is_recursive'] ?? Session::getIsActiveEntityRecursive();

        if (function_exists('renderTwigForm')) {
            $types = $CFG_GLPI['notificationtemplates_types'];
            $typeValues = [];
            foreach ($types as $type) {
                if ($item = getItemForItemtype($type)) {
                    $typeValues[$type] = $item->getTypeName(1);
                }
            }

            $itemtype = $this->fields['itemtype'] ?? 'Ticket';
            $target   = \NotificationTarget::getInstanceByType($itemtype);
            $events   = $target ? $target->getAllEvents() : [Dropdown::EMPTY_VALUE];

            $rand        = mt_rand();
            $itemtypeId  = "dropdown_itemtype$rand";
            $eventId     = "dropdown_event$rand";
            $eventHookJs = sprintf(
                "$.ajax({method: 'POST', url: '%s/plugins/webhook/ajax/dropdownEvent.php', data: {itemtype: $(this).val()}, dataType: 'json', success: function(data) { $('#%s').empty(); $('#%s').append('<option value=\"\">' + '%s' + '</option>'); $.each(data, function(key, value) { $('#%s').append('<option value=\"' + key + '\">' + value + '</option>'); }); }});",
                $CFG_GLPI['root_doc'],
                $eventId,
                $eventId,
                addslashes(Dropdown::EMPTY_VALUE),
                $eventId
            );

            $form = [
                'action'   => $this->getFormURL(),
                'itemtype' => self::class,
                'content'  => [
                    $this->getTypeName(1) => [
                        'visible' => true,
                        'inputs'  => [
                            __('Name') => [
                                'type'  => 'text',
                                'name'  => 'name',
                                'value' => Html::entities_deep($this->fields['name'] ?? ''),
                                'col_md' => 6,
                                'col_lg' => 6,
                            ],
                            __('Active') => [
                                'type'  => 'checkbox',
                                'name'  => 'is_active',
                                'value' => $this->fields['is_active'] ?? 1,
                            ],
                            _n('Type', 'Types', 1) => [
                                'type'   => 'select',
                                'name'   => 'itemtype',
                                'id'     => $itemtypeId,
                                'values' => $typeValues,
                                'value'  => $itemtype,
                                'hooks'  => [
                                    'change' => $eventHookJs,
                                ],
                            ],
                            NotificationEvent::getTypeName(1) => [
                                'type'   => 'select',
                                'name'   => 'event',
                                'id'     => $eventId,
                                'values' => $events,
                                'value'  => $this->fields['event'] ?? '',
                                'display_emptychoice' => true,
                            ],
                            _n('Webhook', 'Webhooks', 1, 'webhook') => [
                                'type'      => 'select',
                                'name'      => 'plugin_webhook_webhooks_id',
                                'itemtype'  => Webhook::class,
                                'value'     => $this->fields['plugin_webhook_webhooks_id'] ?? 0,
                                'condition' => ['is_active' => 1],
                                'display_emptychoice' => false,
                            ],
                            _n('Template', 'Templates', 1, 'webhook') => [
                                'type'      => 'select',
                                'name'      => 'plugin_webhook_templates_id',
                                'itemtype'  => Template::class,
                                'value'     => $this->fields['plugin_webhook_templates_id'] ?? 0,
                                'display_emptychoice' => true,
                            ],
                            __('Comment') => [
                                'type'  => 'textarea',
                                'name'  => 'comment',
                                'value' => Html::entities_deep($this->fields['comment'] ?? ''),
                                'col_md' => 12,
                                'col_lg' => 12,
                            ],
                        ],
                    ],
                ],
            ];

            renderTwigForm($form, '', $this->fields);
            return true;
        }

        return $this->renderLegacyForm($options, $CFG_GLPI);
    }

    private function renderLegacyForm(array $options, array $CFG_GLPI): bool {
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name') . "</td><td>";
        echo Html::input('name', ['value' => $this->fields['name'] ?? '', 'size' => 50]);
        echo "</td>";
        echo "<td>" . __('Active') . "</td><td>";
        Dropdown::showYesNo('is_active', $this->fields['is_active'] ?? 1);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . _n('Type', 'Types', 1) . "</td><td>";
        $rand = Dropdown::showItemTypes('itemtype', $CFG_GLPI['notificationtemplates_types'], [
            'value' => $this->fields['itemtype'] ?? 'Ticket'
        ]);
        // AJAX to update events dropdown when itemtype changes
        $params = ['itemtype' => '__VALUE__'];
        Ajax::updateItemOnSelectEvent(
            "dropdown_itemtype$rand",
            "show_events",
            $CFG_GLPI["root_doc"] . "/ajax/dropdownNotificationEvent.php",
            $params
        );
        echo "</td>";
        echo "<td>" . NotificationEvent::getTypeName(1) . "</td><td>";
        echo "<span id='show_events'>";
        NotificationEvent::dropdownEvents(
            $this->fields['itemtype'] ?? 'Ticket',
            ['value' => $this->fields['event'] ?? '']
        );
        echo "</span>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Webhook', 'webhook') . "</td><td>";
        Dropdown::show('PluginWebhookWebhook', [
            'name' => 'plugin_webhook_webhooks_id',
            'value' => $this->fields['plugin_webhook_webhooks_id'] ?? 0,
            'comments' => false
        ]);
        echo "</td>";
        echo "<td>" . __('Template', 'webhook') . "</td><td>";
        Dropdown::show('PluginWebhookTemplate', [
            'name' => 'plugin_webhook_templates_id',
            'value' => $this->fields['plugin_webhook_templates_id'] ?? 0,
            'comments' => false
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Entity') . "</td><td>";
        Entity::dropdown(['name' => 'entities_id', 'value' => $this->fields['entities_id'] ?? 0]);
        echo "</td>";
        echo "<td>" . __('Recursive') . "</td><td>";
        Dropdown::showYesNo('is_recursive', $this->fields['is_recursive'] ?? 0);
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
            'field'    => 'itemtype',
            'name'     => _n('Type', 'Types', 1),
            'datatype' => 'itemtypename',
        ];

        $tab[] = [
            'id'       => '3',
            'table'    => $this->getTable(),
            'field'    => 'event',
            'name'     => NotificationEvent::getTypeName(1),
            'datatype' => 'specific',
            'additionalfields' => ['itemtype'],
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => '4',
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'            => '5',
            'table'         => Webhook::getTable(),
            'field'         => 'name',
            'name'          => _n('Webhook', 'Webhooks', 1, 'webhook'),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => '6',
            'table'         => Template::getTable(),
            'field'         => 'name',
            'name'          => _n('Template', 'Templates', 1, 'webhook'),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => '80',
            'table'         => 'glpi_entities',
            'field'         => 'completename',
            'name'          => \Entity::getTypeName(1),
            'massiveaction' => false,
            'datatype'      => 'dropdown',
        ];

        return $tab;
    }
}
