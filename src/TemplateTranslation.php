<?php

namespace GlpiPlugin\Webhook;

use CommonDBTM;
use Dropdown;
use Html;
use NotificationTarget;
use NotificationTemplate;
use Plugin;
use Session;
use GlpiPlugin\Webhook\Template;
use Toolbox;

class TemplateTranslation extends CommonDBTM {
    use Permissions;

    public static function getTable($classname = null) {
        return 'glpi_plugin_webhook_template_translations';
    }

    public static function getTypeName($nb = 0) {
        return _n('Webhook template translation', 'Webhook template translations', $nb, 'webhook');
    }

    public static function getFormURL($full = false) {
        return Plugin::getWebDir('webhook') . '/front/templatetranslation.form.php';
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
    `plugin_webhook_templates_id` int(11) NOT NULL,
    `language` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
    `payload_template` longtext COLLATE utf8_unicode_ci,
    `date_creation` timestamp NULL DEFAULT NULL,
    `date_mod` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unicity` (`plugin_webhook_templates_id`,`language`)
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
        return $this->validatePayload($input);
    }

    public function prepareInputForUpdate($input) {
        return $this->validatePayload($input);
    }

    private function validatePayload($input) {
        $payload = $input['payload_template'] ?? '';
        $clean = $payload;
        if (is_string($clean) && strpos($clean, '\\') !== false) {
            $clean = str_replace(["\\r\\n", "\\n", "\\r", "\\t"], ["\n", "\n", "\n", "\t"], $clean);
            $clean = str_replace('\\"', '"', $clean);
        }

        if (!static::isValidJson($clean)) {
            Session::addMessageAfterRedirect(__('Payload must be valid JSON', 'webhook'), false, ERROR);
            return false;
        }

        // Store the cleaned payload back into input so it gets saved
        $input['payload_template'] = $clean;
        return $input;
    }

    public static function isValidJson($content): bool {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Process a webhook payload template using GLPI's NotificationTemplate processing.
     * This ensures full compatibility with all GLPI notification template tags.
     *
     * @param string $template  The JSON payload template with ##tag## placeholders
     * @param array  $data      Tag data from NotificationTarget (##tag## => value format)
     * @return string           Processed JSON payload
     */
    public static function processPayloadTemplate(string $template, array $data): string {
        // Use GLPI's NotificationTemplate::process() for full tag compatibility
        // This handles ##tag##, ##FOREACH##, ##IF##, ##ELSE##, etc.
        return NotificationTemplate::process($template, $data);
    }

    /**
     * Get tag data for a given item and event using GLPI's NotificationTarget.
     * This returns the same data that would be used for email notifications.
     *
     * @param CommonDBTM $item    The item (Ticket, Change, Problem, etc.)
     * @param string     $event   The event name (new, update, solved, etc.)
     * @param array      $options Additional options
     * @return array              Tag data in ##tag## => value format
     */
    public static function getTagDataForItem(\CommonDBTM $item, string $event, array $options = []): array {
        $itemtype = get_class($item);
        $entity = $item->getEntityID();

        $target = NotificationTarget::getInstance($item, $event, $options);
        if (!$target) {
            return [];
        }

        // Get the data that would be used for notification templates
        return $target->getForTemplate($event, $options);
    }

    public function showForm($ID, $options = []) {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Get parent template's itemtype
        $templateId = $this->fields['plugin_webhook_templates_id'] ?? ($options['plugin_webhook_templates_id'] ?? 0);
        $itemtype = 'Ticket'; // default
        if ($templateId) {
            $template = new Template();
            if ($template->getFromDB($templateId)) {
                $itemtype = $template->fields['itemtype'] ?: 'Ticket';
            }
        }

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Template') . "</td><td>";
        Dropdown::show('PluginWebhookTemplate', [
            'name'  => 'plugin_webhook_templates_id',
            'value' => $this->fields['plugin_webhook_templates_id'] ?? 0,
            'comments' => false
        ]);
        echo "</td>";
        echo "<td>" . __('Language') . "</td><td>";
        Dropdown::showLanguages('language', [
            'value' => $this->fields['language'] ?? '',
            'display_emptychoice' => true,
            'emptylabel' => __('Default language')
        ]);
        echo "</td></tr>";

        // Button to show available tags
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4' class='center'>";
        $rand = mt_rand();
        echo "<a class='btn btn-outline-secondary' href='#' onclick='$(\"#webhook-tags-$rand\").toggle(); return false;'>";
        echo "<i class='fas fa-tags'></i> " . __('Show available tags', 'webhook');
        echo "</a>";
        echo "</td></tr>";

        // Hidden div for tags (collapsed by default)
        echo "<tr class='tab_bg_1' id='webhook-tags-$rand' style='display:none;'>";
        echo "<td colspan='4'>";
        self::showAvailableTags($itemtype);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4'>";
        echo "<textarea name='payload_template' cols='100' rows='15'>" . Html::entities_deep($this->fields['payload_template'] ?? self::getDefaultTemplate()) . "</textarea>";
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    /**
     * Display available notification tags for the given itemtype.
     * Uses GLPI's NotificationTarget system for full tag compatibility.
     *
     * @param string $itemtype
     */
    public static function showAvailableTags(string $itemtype): void {
        $target = NotificationTarget::getInstanceByType($itemtype);
        if (!$target) {
            echo "<div class='center'>" . __('No tags available for this type', 'webhook') . "</div>";
            return;
        }

        // Trigger tag generation
        $target->getTags();

        echo "<div class='center'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th>" . __('Tag') . "</th>";
        echo "<th>" . __('Label') . "</th>";
        echo "<th>" . _n('Event', 'Events', 1) . "</th>";
        echo "<th>" . _n('Type', 'Types', 1) . "</th>";
        echo "</tr>";

        $tags = [];
        foreach ($target->tag_descriptions as $tag_type => $infos) {
            foreach ($infos as $key => $val) {
                $infos[$key]['type'] = $tag_type;
            }
            $tags = array_merge($tags, $infos);
        }
        ksort($tags);

        foreach ($tags as $tag => $values) {
            if ($values['events'] == NotificationTarget::TAG_FOR_ALL_EVENTS) {
                $event = __('All');
            } else {
                $event = is_array($values['events']) ? implode(', ', $values['events']) : $values['events'];
            }

            $action = $values['foreach'] ? __('List of values') : __('Single value');

            echo "<tr class='tab_bg_1'>";
            echo "<td><code>" . Html::entities_deep($tag) . "</code></td>";
            echo "<td>";
            if ($values['type'] == NotificationTarget::TAG_LANGUAGE) {
                printf(__('%1$s: %2$s'), __('Label'), $values['label']);
            } else {
                echo $values['label'];
            }
            echo "</td>";
            echo "<td>" . $event . "</td>";
            echo "<td>" . $action . "</td>";
            echo "</tr>";
        }

        echo "</table></div>";
    }

    private static function getDefaultTemplate(): string {
        return Template::getDefaultPayloadTemplate();
    }

    public static function showForTemplate(int $templateId): void {
        global $DB;

        $translations = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['plugin_webhook_templates_id' => $templateId],
            'ORDER' => 'language ASC'
        ]);

        echo "<div class='spaced'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th>" . _n('Webhook template translation', 'Webhook template translations', 2, 'webhook') . "</th></tr>";

        foreach ($translations as $row) {
            $lang = $row['language'] === '' ? __('Default language') : $row['language'];
            $preview = Toolbox::substr(trim($row['payload_template']), 0, 80);
            $url = Plugin::getWebDir('webhook') . '/front/templatetranslation.form.php?id=' . (int)$row['id'];
            echo "<tr class='tab_bg_2'><td><a href='{$url}'>" . Html::entities_deep($lang) . "</a> — " . Html::entities_deep($preview) . "</td></tr>";
        }

        $addUrl = Plugin::getWebDir('webhook') . '/front/templatetranslation.form.php?plugin_webhook_templates_id=' . $templateId;
        echo "<tr class='tab_bg_2'><td class='center'><a class='vsubmit' href='{$addUrl}'>" . _sx('button', 'Add') . "</a></td></tr>";

        echo "</table>";
        echo "</div>";
    }
}

class_alias(TemplateTranslation::class, 'PluginWebhookTemplateTranslation');
