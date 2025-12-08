<?php

use GlpiPlugin\Webhook\Template;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

if (!isset($_GET['id'])) {
    $_GET['id'] = '';
}

$template = new Template();

if (isset($_POST['add'])) {
    $template->check(-1, CREATE, $_POST);
    if ($newID = $template->add($_POST)) {
        if ($_SESSION['glpibackcreated']) {
            Html::redirect($template->getLinkURL());
        }
    }
    Html::back();

} else if (isset($_POST['update'])) {
    $template->check($_POST['id'], UPDATE);
    $template->update($_POST);
    Html::back();

} else if (isset($_POST['purge'])) {
    $template->check($_POST['id'], PURGE);
    $template->delete($_POST, 1);
    $template->redirectToList();

} else if (isset($_POST['delete'])) {
    $template->check($_POST['id'], DELETE);
    $template->delete($_POST);
    $template->redirectToList();

} else {
    Html::header(Template::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Webhook\WebhookMenu', 'template');
    $template->display(['id' => $_GET['id']]);
    Html::footer();
}
