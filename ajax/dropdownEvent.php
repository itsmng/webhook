<?php

use GlpiPlugin\Webhook\Permissions;

include('../../../inc/includes.php');
Session::checkLoginUser();
if (!Session::haveRight('plugin_webhook_use', READ)) {
    Html::displayRightError();
}

$itemtype = $_POST['itemtype'] ?? $_GET['itemtype'] ?? '';
$itemtype = htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8');

// Validate itemtype to prevent injection
if (!empty($itemtype) && !class_exists($itemtype)) {
    $itemtype = '';
}
$events = [];
if (!empty($itemtype)) {
    // Basic defaults; can be extended by environment-specific notification targets
    $events = [
        'add'    => __('Create'),
        'update' => __('Update'),
        'delete' => __('Delete'),
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
