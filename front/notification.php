<?php

use GlpiPlugin\Webhook\Notification;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_config', READ);

Html::header(Notification::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Webhook\WebhookMenu', 'notification');

Search::show("PluginWebhookNotification");

Html::footer();
