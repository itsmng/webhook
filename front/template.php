<?php

use GlpiPlugin\Webhook\Template;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

Html::header(Template::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Webhook\WebhookMenu', 'template');

Search::show(Template::class);

Html::footer();
