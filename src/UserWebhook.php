<?php

namespace GlpiPlugin\Webhook;

use CommonDBRelation;
use CommonGLPI;
use Html;
use Dropdown;
use Plugin;
use Session;
use User;

class UserWebhook extends CommonDBRelation {
    use Permissions;

    // Relation: User <-> Webhook
    public static $itemtype_1 = 'User';
    public static $items_id_1 = 'users_id';
    public static $itemtype_2 = 'PluginWebhookWebhook';
    public static $items_id_2 = 'plugin_webhook_webhooks_id';

    public static $checkItem_2_Rights = self::DONT_CHECK_ITEM_RIGHTS;

    public static function getTable($classname = null) {
        return 'glpi_plugin_webhook_user_webhooks';
    }

    public static function getTypeName($nb = 0) {
        return _n('User webhook', 'User webhooks', $nb, 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/userwebhook.form.php';
    }

    public static function canView() { return self::canUse(); }
    public static function canCreate() { return self::canConfig(); }
    public static function canUpdate() { return self::canConfig(); }
    public static function canDelete() { return self::canConfig(); }
    public static function canPurge() { return self::canConfig(); }

    public static function install() {
        global $DB;
        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $query = <<<SQL
CREATE TABLE `$table` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `users_id` int(11) NOT NULL,
    `plugin_webhook_webhooks_id` int(11) NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT '1',
    `date_creation` timestamp NULL DEFAULT NULL,
    `date_mod` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unicity` (`users_id`,`plugin_webhook_webhooks_id`),
    KEY `users_id` (`users_id`),
    KEY `plugin_webhook_webhooks_id` (`plugin_webhook_webhooks_id`)
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

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() === 'User' && self::canView()) {
            $nb = 0;
            if ($_SESSION['glpishow_count_on_tabs']) {
                $nb = countElementsInTable(self::getTable(), ['users_id' => $item->getID()]);
            }
            return self::createTabEntry(__('Webhooks', 'webhook'), $nb);
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() === 'User') {
            self::showForUser($item);
        }
        return true;
    }

    /**
     * Show webhooks assigned to a user
     *
     * @param User $user
     */
    public static function showForUser(User $user) {
        global $DB;

        $ID = $user->getID();
        if (!$user->can($ID, READ)) {
            return false;
        }

        $canedit = self::canConfig() || Session::haveRight('plugin_webhook_use', UPDATE);
        $rand = mt_rand();

        // Add form
        if ($canedit) {
            echo "<div class='firstbloc'>";
            echo "<form name='userwebhook_form$rand' id='userwebhook_form$rand' method='post' action='" . self::getFormURL() . "'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'><th colspan='4'>" . __('Add a webhook to user', 'webhook') . "</th></tr>";

            echo "<tr class='tab_bg_2'>";
            echo "<td>" . __('Webhook', 'webhook') . "</td>";
            echo "<td>";
            echo Html::hidden('users_id', ['value' => $ID]);
            Dropdown::show('PluginWebhookWebhook', [
                'name'      => 'plugin_webhook_webhooks_id',
                'entity'    => $user->getEntityID(),
                'comments'  => false,
                'condition' => ['is_active' => 1]
            ]);
            echo "</td>";
            echo "<td>" . __('Active') . "</td>";
            echo "<td>";
            Dropdown::showYesNo('is_active', 1);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_2'>";
            echo "<td colspan='4' class='center'>";
            echo "<input type='submit' name='add' value=\"" . _sx('button', 'Add') . "\" class='submit'>";
            echo "</td></tr>";

            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }

        // List existing webhooks for user
        $iterator = $DB->request([
            'SELECT' => [
                self::getTable() . '.*',
                Webhook::getTable() . '.name AS webhook_name',
                Webhook::getTable() . '.url AS webhook_url'
            ],
            'FROM'   => self::getTable(),
            'LEFT JOIN' => [
                Webhook::getTable() => [
                    'FKEY' => [
                        self::getTable()    => 'plugin_webhook_webhooks_id',
                        Webhook::getTable() => 'id'
                    ]
                ]
            ],
            'WHERE'  => ['users_id' => $ID],
            'ORDER'  => 'webhook_name ASC'
        ]);

        $num = count($iterator);

        echo "<div class='spaced'>";
        Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);

        if ($canedit && $num) {
            $massiveactionparams = [
                'num_displayed' => min($_SESSION['glpilist_limit'], $num),
                'container'     => 'mass' . __CLASS__ . $rand
            ];
            Html::showMassiveActions($massiveactionparams);
        }

        echo "<table class='tab_cadre_fixehov'>";
        $header_begin  = "<tr>";
        $header_top    = '';
        $header_bottom = '';
        $header_end    = '';

        if ($canedit && $num) {
            $header_begin  .= "<th width='10'>";
            $header_top    .= Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header_bottom .= Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header_end    .= "</th>";
        }

        $header_end .= "<th>" . __('Webhook', 'webhook') . "</th>";
        $header_end .= "<th>" . __('URL') . "</th>";
        $header_end .= "<th>" . __('Active') . "</th>";
        $header_end .= "</tr>";

        echo $header_begin . $header_top . $header_end;

        if ($num > 0) {
            foreach ($iterator as $data) {
                echo "<tr class='tab_bg_1'>";

                if ($canedit) {
                    echo "<td width='10'>";
                    Html::showMassiveActionCheckBox(__CLASS__, $data['id']);
                    echo "</td>";
                }

                echo "<td>";
                $webhookUrl = Plugin::getWebDir('webhook') . '/front/webhook.form.php?id=' . $data['plugin_webhook_webhooks_id'];
                echo "<a href='$webhookUrl'>" . Html::entities_deep($data['webhook_name'] ?? __('Unknown')) . "</a>";
                echo "</td>";

                echo "<td>" . Html::entities_deep($data['webhook_url'] ?? '') . "</td>";

                echo "<td>" . Dropdown::getYesNo($data['is_active']) . "</td>";

                echo "</tr>";
            }
        } else {
            echo "<tr class='tab_bg_1'><td colspan='4' class='center'>" . __('No webhook assigned to this user', 'webhook') . "</td></tr>";
        }

        echo $header_begin . $header_bottom . $header_end;
        echo "</table>";

        if ($canedit && $num) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
        }

        Html::closeForm();
        echo "</div>";
    }

    /**
     * Get webhooks for a specific user
     *
     * @param int $users_id
     * @param bool $active_only Only return active webhooks
     * @return array
     */
    public static function getWebhooksForUser(int $users_id, bool $active_only = true): array {
        global $DB;

        $criteria = ['users_id' => $users_id];
        if ($active_only) {
            $criteria['is_active'] = 1;
        }

        $iterator = $DB->request([
            'SELECT' => ['plugin_webhook_webhooks_id'],
            'FROM'   => self::getTable(),
            'WHERE'  => $criteria
        ]);

        $webhooks = [];
        foreach ($iterator as $row) {
            $webhooks[] = $row['plugin_webhook_webhooks_id'];
        }
        return $webhooks;
    }
}
