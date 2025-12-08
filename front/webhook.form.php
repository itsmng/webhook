<?php

use GlpiPlugin\Webhook\Webhook;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

if (!isset($_GET['id'])) {
    $_GET['id'] = '';
}

$webhook = new Webhook();

if (isset($_POST['add'])) {
    $webhook->check(-1, CREATE, $_POST);
    if ($newID = $webhook->add($_POST)) {
        if ($_SESSION['glpibackcreated']) {
            Html::redirect($webhook->getLinkURL());
        }
    }
    Html::back();

} else if (isset($_POST['update'])) {
    $webhook->check($_POST['id'], UPDATE);
    $webhook->update($_POST);
    Html::back();

} else if (isset($_POST['purge'])) {
    $webhook->check($_POST['id'], PURGE);
    $webhook->delete($_POST, 1);
    $webhook->redirectToList();

} else if (isset($_POST['delete'])) {
    $webhook->check($_POST['id'], DELETE);
    $webhook->delete($_POST);
    $webhook->redirectToList();

} else {
    Html::header(Webhook::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Webhook\WebhookMenu', 'webhook');
    $webhook->display(['id' => $_GET['id']]);
    Html::footer();
}
