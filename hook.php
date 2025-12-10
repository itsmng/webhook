<?php

use GlpiPlugin\Webhook\Config;
use GlpiPlugin\Webhook\Notification;
use GlpiPlugin\Webhook\NotificationEventWebhook;
use GlpiPlugin\Webhook\Profile;
use GlpiPlugin\Webhook\Template;
use GlpiPlugin\Webhook\TemplateTranslation;
use GlpiPlugin\Webhook\UserWebhook;
use GlpiPlugin\Webhook\Webhook;
use GlpiPlugin\Webhook\WebhookTemplate;

/**
 * Install webhook plugin schema and seed defaults.
 */
function plugin_webhook_install()
{
    set_time_limit(900);
    ini_set('memory_limit', '2048M');

    $classesToInstall = [
        Config::class,
        Profile::class,
        Webhook::class,
        Template::class,
        TemplateTranslation::class,
        WebhookTemplate::class,
        Notification::class,
        UserWebhook::class,
    ];

    echo "<center>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<tr><th>" . __("MySQL tables installation", "webhook") . "<th></tr>";
    echo "<tr class='tab_bg_1'>";
    echo "<td align='center'>";

    foreach ($classesToInstall as $class) {
        if (!call_user_func([$class, 'install'])) {
            return false;
        }
    }

    // Seed default templates after all tables are created
    Template::seedDefaultTemplates();

    // Register notification mode on install to ensure mode exists before enabling rules
    Notification_NotificationTemplate::registerMode('webhook', __('Webhook', 'webhook'), 'webhook');

    echo "</td>";
    echo "</tr>";
    echo "</table></center>";

    return true;
}

/**
 * Uninstall webhook plugin schema.
 */
function plugin_webhook_uninstall()
{
    echo "<center>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<tr><th>" . __("MySQL tables uninstallation", "webhook") . "<th></tr>";
    echo "<tr class='tab_bg_1'>";
    echo "<td align='center'>";

    $classesToUninstall = [
        UserWebhook::class,
        Notification::class,
        WebhookTemplate::class,
        TemplateTranslation::class,
        Template::class,
        Webhook::class,
        Profile::class,
        Config::class,
    ];

    foreach ($classesToUninstall as $class) {
        if (!call_user_func([$class, 'uninstall'])) {
            return false;
        }
    }

    echo "</td>";
    echo "</tr>";
    echo "</table></center>";

    return true;
}
