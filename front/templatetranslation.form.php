<?php

use GlpiPlugin\Webhook\TemplateTranslation;
use GlpiPlugin\Webhook\Template;

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

if (!isset($_GET['id'])) {
    $_GET['id'] = '';
}

$translation = new TemplateTranslation();

if (isset($_POST['add'])) {
    $translation->check(-1, CREATE, $_POST);
    if ($newID = $translation->add($_POST)) {
        // Redirect back to parent template
        $tpl = new Template();
        if ($tpl->getFromDB($_POST['plugin_webhook_templates_id'] ?? 0)) {
            Html::redirect($tpl->getLinkURL());
        }
    }
    Html::back();

} else if (isset($_POST['update'])) {
    $translation->check($_POST['id'], UPDATE);
    $translation->update($_POST);
    Html::back();

} else if (isset($_POST['purge'])) {
    $translation->check($_POST['id'], PURGE);
    $parentId = $translation->fields['plugin_webhook_templates_id'] ?? 0;
    $translation->delete($_POST, 1);
    $tpl = new Template();
    if ($tpl->getFromDB($parentId)) {
        Html::redirect($tpl->getLinkURL());
    }
    Html::back();

} else {
    Html::header(TemplateTranslation::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'config', 'webhook');
    $options = ['id' => $_GET['id']];
    if (isset($_GET['plugin_webhook_templates_id'])) {
        $options['plugin_webhook_templates_id'] = (int)$_GET['plugin_webhook_templates_id'];
    }
    $translation->display($options);
    Html::footer();
}
