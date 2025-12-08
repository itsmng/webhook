<?php

use GlpiPlugin\Webhook\UserWebhook;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

$item = new UserWebhook();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    if ($item->add($_POST)) {
        Session::addMessageAfterRedirect(__('Webhook assigned to user', 'webhook'), true, INFO);
    }
    Html::back();

} else if (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    if ($item->update($_POST)) {
        Session::addMessageAfterRedirect(__('Item updated successfully'), true, INFO);
    }
    Html::back();

} else if (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    if ($item->delete($_POST, 1)) {
        Session::addMessageAfterRedirect(__('Item deleted successfully'), true, INFO);
    }
    Html::back();

} else if (isset($_POST['delete'])) {
    $item->check($_POST['id'], DELETE);
    if ($item->delete($_POST)) {
        Session::addMessageAfterRedirect(__('Item deleted successfully'), true, INFO);
    }
    Html::back();
}

// Direct access - show form if id provided
if (isset($_GET['id']) && $_GET['id'] > 0) {
    Html::header(UserWebhook::getTypeName(1), $_SERVER['PHP_SELF'], 'config', 'webhook');
    $item->display(['id' => $_GET['id']]);
    Html::footer();
}
