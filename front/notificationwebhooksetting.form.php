<?php

use GlpiPlugin\Webhook\Config;
use GlpiPlugin\Webhook\NotificationWebhookSetting;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    Config::setValues([
        'notifications_webhook'   => isset($_POST['notifications_webhook']) ? 1 : 0,
        'webhook_default_timeout' => max(1, (int)($_POST['webhook_default_timeout'] ?? 10)),
        'webhook_verify_ssl'      => isset($_POST['webhook_verify_ssl']) ? 1 : 0,
    ]);
    Html::back();
}

Html::header(_n('Notification', 'Notifications', Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'config', 'notification', 'config');

$setting = new NotificationWebhookSetting();
$setting->display(['id' => 1]);

Html::footer();
