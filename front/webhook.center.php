<?php

use GlpiPlugin\Webhook\Webhook;
use GlpiPlugin\Webhook\Template;
use GlpiPlugin\Webhook\Notification;
use GlpiPlugin\Webhook\Config;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

Html::header(__('Webhooks', 'webhook'), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Webhook\WebhookMenu');

$links = [
    [
        'label' => _n('Webhook', 'Webhooks', Session::getPluralNumber(), 'webhook'),
        'url'   => Webhook::getSearchURL(false),
        'icon'  => 'fas fa-link',
    ],
    [
        'label' => _n('Webhook template', 'Webhook templates', Session::getPluralNumber(), 'webhook'),
        'url'   => Template::getSearchURL(false),
        'icon'  => 'fas fa-file-code',
    ],
    [
        'label' => _n('Webhook notification rule', 'Webhook notification rules', Session::getPluralNumber(), 'webhook'),
        'url'   => Notification::getSearchURL(false),
        'icon'  => 'fas fa-bell',
    ],
    [
        'label' => __('Webhook configuration', 'webhook'),
        'url'   => Config::getFormURL(false),
        'icon'  => 'fas fa-cog',
    ],
];

echo "<div class='center spaced'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Webhook administration', 'webhook') . "</th></tr>";
foreach ($links as $link) {
    echo "<tr class='tab_bg_2'><td class='center'><i class='" . $link['icon'] . "'></i></td>";
    echo "<td><a href='" . $link['url'] . "'>" . $link['label'] . "</a></td></tr>";
}
echo "</table>";
echo "</div>";

Html::footer();
