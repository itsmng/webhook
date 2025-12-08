<?php

use GlpiPlugin\Webhook\Webhook;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

Html::header(Webhook::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Webhook\WebhookMenu', 'webhook');

Search::show(Webhook::class);

Html::footer();
