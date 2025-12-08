<?php

namespace GlpiPlugin\Webhook;

use CommonDBTM;
use CommonGLPI;
use Html;
use Plugin;
use Profile as CoreProfile;
use ProfileRight;
use Session;

class Profile extends CommonDBTM {
    
    const SUPER_ADMIN_PROFILE_ID = 4;
    use Permissions;

    public static function getTable($classname = null) {
        return 'glpi_plugin_webhook_profiles';
    }

    public static function getTypeName($nb = 0) {
        return _n('Webhook right', 'Webhook rights', $nb, 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/profile.form.php';
    }

    public static function install() {
        global $DB;
        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $query = <<<SQL
CREATE TABLE `$table` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `profiles_id` int(11) NOT NULL,
    `rights` text COLLATE utf8_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `profiles_id` (`profiles_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;
            $DB->queryOrDie($query, $DB->error());
        }


        self::addDefaultProfileInfos(self::SUPER_ADMIN_PROFILE_ID, [
            'plugin_webhook_config' => READ + UPDATE + DELETE,
            'plugin_webhook_use'    => READ + UPDATE,
        ]);

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

    public static function changeProfile() {
        $prof = new self();
        if ($prof->getFromDB($_SESSION['glpiactiveprofile']['id'])) {
            $_SESSION['glpi_plugin_webhook_profile'] = $prof->fields;
        } else {
            unset($_SESSION['glpi_plugin_webhook_profile']);
        }
    }

    public static function addDefaultProfileInfos($profiles_id, $rights) {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (!countElementsInTable('glpi_profilerights', ['profiles_id' => $profiles_id, 'name' => $right])) {
                $profileRight->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                    'rights'      => $value,
                ]);
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if (Session::haveRight('profile', UPDATE) && $item->getType() === CoreProfile::class) {
            return __('Webhook', 'webhook');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() === CoreProfile::class) {
            $ID = $item->getID();
            $prof = new self();
            foreach (self::getRightsGeneral() as $right) {
                self::addDefaultProfileInfos($ID, [$right['field'] => $right['default']]);
            }
            $prof->showForm($ID);
        }
        return true;
    }

    public static function getRightsGeneral() {
        return [
            [
                'itemtype' => self::class,
                'label'    => __('Configure webhooks', 'webhook'),
                'field'    => 'plugin_webhook_config',
                'rights'   => [READ => __('Read'), UPDATE => __('Write')],
                'default'  => READ + UPDATE,
            ],
            [
                'itemtype' => self::class,
                'label'    => __('Use webhooks', 'webhook'),
                'field'    => 'plugin_webhook_use',
                'rights'   => [READ => __('Read'), UPDATE => __('Write')],
                'default'  => READ,
            ],
        ];
    }

    public function showForm($profiles_id = 0, $openform = true, $closeform = true) {
        if (!Session::haveRight('profile', READ)) {
            return false;
        }

        echo "<div class='firstbloc'>";
        $canedit = Session::haveRight('profile', UPDATE);
        if ($canedit && $openform) {
            $profile = new CoreProfile();
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        $profile = new CoreProfile();
        $profile->getFromDB($profiles_id);
        $rights = $this->getRightsGeneral();
        $profile->displayRightsChoiceMatrix($rights, ['default_class' => 'tab_bg_2', 'title' => __('General')]);

        if ($canedit && $closeform) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
            Html::closeForm();
        }

        echo "</div>";
        return true;
    }
}

class_alias(Profile::class, 'PluginWebhookProfile');