<?php

use GlpiPlugin\Webhook\Config;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_config', UPDATE);

if (isset($_POST['update'])) {
    Config::setValues([
        'notifications_webhook'   => !empty($_POST['notifications_webhook']) ? 1 : 0,
        'webhook_default_timeout' => max(1, (int)($_POST['webhook_default_timeout'] ?? 10)),
        'webhook_verify_ssl'      => !empty($_POST['webhook_verify_ssl']) ? 1 : 0,
    ]);
    Html::back();
}

Html::header(__('Webhook configuration', 'webhook'), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Webhook\WebhookMenu', 'config');

$config = new Config();
$config->showConfigForm();

Html::footer();
