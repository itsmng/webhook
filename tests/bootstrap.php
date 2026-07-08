<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$projectRoot = dirname($pluginRoot, 2);
$glpiVarDir = $pluginRoot . '/tests/tmp/glpi-var';

if (!is_dir($glpiVarDir)) {
    mkdir($glpiVarDir, 0777, true);
}

putenv('GLPI_VAR_DIR=' . $glpiVarDir);

require_once $projectRoot . '/vendor/autoload.php';

if (!class_exists(\atoum\atoum::class) && class_exists(\atoum\atoum\test::class)) {
    class_alias(\atoum\atoum\test::class, \atoum\atoum::class);
}

require_once $projectRoot . '/tests/bootstrap.php';
require_once $pluginRoot . '/setup.php';
require_once $pluginRoot . '/hook.php';

foreach (
    [
        \GlpiPlugin\Webhook\Config::class,
        \GlpiPlugin\Webhook\Profile::class,
        \GlpiPlugin\Webhook\Webhook::class,
        \GlpiPlugin\Webhook\Template::class,
        \GlpiPlugin\Webhook\TemplateTranslation::class,
        \GlpiPlugin\Webhook\WebhookTemplate::class,
        \GlpiPlugin\Webhook\Notification::class,
        \GlpiPlugin\Webhook\UserWebhook::class,
    ] as $class
) {
    ob_start();
    $class::install();
    ob_end_clean();
}

if (!countElementsInTable(\GlpiPlugin\Webhook\Template::getTable())) {
    \GlpiPlugin\Webhook\Template::seedDefaultTemplates();
}
