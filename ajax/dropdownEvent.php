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
    // Get events from NotificationTarget like core GLPI does
    $target = \NotificationTarget::getInstanceByType($itemtype);
    if ($target) {
        $events = $target->getAllEvents();
    }
}

header('Content-Type: application/json');
echo json_encode($events);
