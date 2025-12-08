<?php

use GlpiPlugin\Webhook\Notification;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_config', READ);

$item = new Notification();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    if ($newId = $item->add($_POST)) {
        Html::redirect(Notification::getFormURL() . '?id=' . $newId);
    }
    Html::back();
} else if (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} else if (isset($_POST['delete'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST);
    $item->redirectToList();
} else if (isset($_POST['restore'])) {
    $item->check($_POST['id'], DELETE);
    $item->restore($_POST);
    Html::back();
} else if (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, 1);
    $item->redirectToList();
}

Html::header(Notification::getTypeName(1), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Webhook\WebhookMenu', 'notification');

$item->display(['id' => $_GET['id'] ?? 0]);

Html::footer();
