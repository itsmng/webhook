<?php

use GlpiPlugin\Webhook\WebhookTemplate;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

$item = new WebhookTemplate();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    $item->add($_POST);
    Html::back();
} else if (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} else if (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, 1);
    Html::back();
}

// This page is typically accessed embedded as a tab
// If accessed directly, display the form
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    Html::header(WebhookTemplate::getTypeName(1), $_SERVER['PHP_SELF'], 'config', 'webhook');
    $item->display(['id' => $_GET['id'] ?? 0]);
    Html::footer();
}
