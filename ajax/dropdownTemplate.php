<?php

include('../../../inc/includes.php');

Session::checkRight('plugin_webhook_use', READ);

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

$itemtype = $_POST['itemtype'] ?? '';
$value    = (int)($_POST['value'] ?? 0);

// Validate itemtype against known classes
if (!empty($itemtype) && !class_exists($itemtype)) {
   $itemtype = '';
}

Dropdown::show('GlpiPlugin\Webhook\Template', [
   'name'                  => 'plugin_webhook_templates_id',
   'value'                 => $value,
   'condition'             => ['itemtype' => $itemtype ?: null],
   'comments'              => false,
   'display_emptychoice'   => true,
]);
