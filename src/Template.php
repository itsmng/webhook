<?php

namespace GlpiPlugin\Webhook;

use CommonDBTM;
use Dropdown;
use Html;
use Plugin;
use CommonGLPI;
use Session;

class Template extends CommonDBTM {
    use Permissions;

    public static $rightname = 'plugin_webhook_use';
    public $dohistory = true;

    public static function getTable($classname = null) {
        return 'glpi_plugin_webhook_templates';
    }

    public static function getTypeName($nb = 0) {
        return _n('Webhook template', 'Webhook templates', $nb, 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/template.form.php';
    }

    public static function getSearchURL($full = true) {
        return Plugin::getWebDir('webhook') . '/front/template.php';
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
    `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `itemtype` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `comment` text COLLATE utf8_unicode_ci DEFAULT NULL,
    `date_creation` timestamp NULL DEFAULT NULL,
    `date_mod` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;
            $DB->queryOrDie($query, $DB->error());
        }
        return true;
    }

    /**
     * Seed default webhook templates for common ITIL types.
     */
    public static function seedDefaultTemplates(): void {
        $defaults = [
            [
                'name'     => 'Default Ticket Webhook',
                'itemtype' => 'Ticket',
                'payload'  => self::getDefaultPayloadForItemtype('Ticket'),
            ],
            [
                'name'     => 'Default Change Webhook',
                'itemtype' => 'Change',
                'payload'  => self::getDefaultPayloadForItemtype('Change'),
            ],
            [
                'name'     => 'Default Problem Webhook',
                'itemtype' => 'Problem',
                'payload'  => self::getDefaultPayloadForItemtype('Problem'),
            ],
        ];

        foreach ($defaults as $tplData) {
            $template = new self();
            $templateId = $template->add([
                'name'     => $tplData['name'],
                'itemtype' => $tplData['itemtype'],
                'comment'  => __('Auto-generated default template', 'webhook'),
            ]);

            if ($templateId) {
                // Add default translation (empty language = default)
                $translation = new TemplateTranslation();
                $translation->add([
                    'plugin_webhook_templates_id' => $templateId,
                    'language'                    => '',
                    'payload_template'            => $tplData['payload'],
                ]);
            }
        }
    }

    /**
     * Get a default payload template for a given itemtype using standard GLPI tags.
     */
    public static function getDefaultPayloadForItemtype(string $itemtype): string {
        $prefix = strtolower($itemtype);

        $payload = [
            'event'        => '##' . $prefix . '.action##',
            'id'           => '##' . $prefix . '.id##',
            'title'        => '##' . $prefix . '.title##',
            'description'  => '##' . $prefix . '.description##',
            'status'       => '##' . $prefix . '.status##',
            'priority'     => '##' . $prefix . '.priority##',
            'urgency'      => '##' . $prefix . '.urgency##',
            'impact'       => '##' . $prefix . '.impact##',
            'category'     => '##' . $prefix . '.category##',
            'url'          => '##' . $prefix . '.url##',
            'entity'       => '##' . $prefix . '.entity##',
            'creationdate' => '##' . $prefix . '.creationdate##',
            'closedate'    => '##' . $prefix . '.closedate##',
            'solvedate'    => '##' . $prefix . '.solvedate##',
            'authors'      => '##' . $prefix . '.authors##',
            'openbyuser'   => '##' . $prefix . '.openbyuser##',
            'lastupdater'  => '##' . $prefix . '.lastupdater##',
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function uninstall() {
        global $DB;
        $table = self::getTable();
        if ($DB->tableExists($table)) {
            $DB->queryOrDie("DROP TABLE `$table`", $DB->error());
        }
        return true;
    }

    public static function getDefaultPayloadTemplate(): string {
        return self::getDefaultPayloadForItemtype('Ticket');
    }

    public function showForm($ID, $options = []) {
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name') . "</td><td colspan='3'>";
        echo Html::input('name', ['value' => $this->fields['name'] ?? '', 'size' => 50]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Item type') . "</td><td colspan='3'>";
        Dropdown::showItemTypes('itemtype', $CFG_GLPI['notificationtemplates_types'], ['value' => $this->fields['itemtype'] ?? 'Ticket']);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Comment') . "</td><td colspan='3'>";
        echo Html::textarea(['name' => 'comment', 'value' => $this->fields['comment'] ?? '', 'cols' => 80, 'rows' => 3]);
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() === self::class && self::canView()) {
            return _n('Webhook template translation', 'Webhook template translations', 2, 'webhook');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() === self::class && self::canView()) {
            TemplateTranslation::showForTemplate($item->getID());
        }
        return true;
    }

    public function defineTabs($options = []) {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(self::class, $tabs, $options);
        return $tabs;
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

class_alias(Template::class, 'PluginWebhookTemplate');
