<?php

global $CFG_GLPI;

define('WEBHOOK_VERSION', '1.0.0');
define('WEBHOOK_ITSMNG_MIN_VERSION', '2.0');

// Inject the host autoloader and register our PSR-4 namespace (same pattern as callmanager)
$hostLoader = require __DIR__ . '/../../vendor/autoload.php';
$hostLoader->addPsr4('GlpiPlugin\\Webhook\\', __DIR__ . '/src/');

// Provide legacy class aliases so GLPI hooks resolving PluginWebhook* still work
spl_autoload_register(function ($class) {
   $map = [
      'PluginWebhookConfig'                   => GlpiPlugin\Webhook\Config::class,
      'PluginWebhookProfile'                  => GlpiPlugin\Webhook\Profile::class,
      'PluginWebhookWebhook'                  => GlpiPlugin\Webhook\Webhook::class,
      'PluginWebhookTemplate'                 => GlpiPlugin\Webhook\Template::class,
      'PluginWebhookTemplateTranslation'      => GlpiPlugin\Webhook\TemplateTranslation::class,
      'PluginWebhookNotification'             => GlpiPlugin\Webhook\Notification::class,
      'PluginWebhookNotificationEventWebhook' => GlpiPlugin\Webhook\NotificationEventWebhook::class,
      'PluginWebhookWebhookTemplate'          => GlpiPlugin\Webhook\WebhookTemplate::class,
      'PluginWebhookUserWebhook'              => GlpiPlugin\Webhook\UserWebhook::class,
      'PluginWebhookNotificationWebhookSetting' => GlpiPlugin\Webhook\NotificationWebhookSetting::class,
      'PluginWebhookMenu'                     => GlpiPlugin\Webhook\WebhookMenu::class,
   ];
   if (isset($map[$class]) && !class_exists($class)) {
      class_alias($map[$class], $class);
   }
});

use GlpiPlugin\Webhook\Config;
use GlpiPlugin\Webhook\Profile;
use GlpiPlugin\Webhook\UserWebhook;
use GlpiPlugin\Webhook\WebhookMenu;

/**
 * Define the plugin's version and informations
 *
 * @return array [name, version, author, homepage, license]
 */
function plugin_version_webhook() {
   return [
      'name'     => 'Webhook Plugin',
      'version'  => WEBHOOK_VERSION,
      'author'   => 'ITSMNG Team',
      'homepage' => 'https://github.com/itsmng/plugin-webhook',
      'license'  => '<a href="../plugins/webhook/LICENSE" target="_blank">GPLv3</a>',
   ];
}

/**
 * Initialize all classes and generic variables of the plugin
 */
function plugin_init_webhook() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['webhook'] = true;

      $PLUGIN_HOOKS['add_javascript']['webhook'] = [
         '/plugins/webhook/js/webhook-headers.js'
      ];

      // Add a Setup submenu entry pointing to the webhook hub
      $PLUGIN_HOOKS['menu_toadd']['webhook']['config'] = WebhookMenu::class;

   // Tabs and profile integration
   Plugin::registerClass(Profile::class, ['addtabon' => \Profile::class]);
   Plugin::registerClass(Config::class, ['addtabon' => \Config::class]);
   Plugin::registerClass(UserWebhook::class, ['addtabon' => User::class]);

   $PLUGIN_HOOKS['change_profile']['webhook'] = [Profile::class, 'changeProfile'];

   if (Session::haveRight('plugin_webhook_config', UPDATE)) {
      $PLUGIN_HOOKS['config_page']['webhook'] = 'front/config.form.php';
   }

   // Register notification mode
   Notification_NotificationTemplate::registerMode('webhook', __('Webhook', 'webhook'), 'webhook');
}

/**
 * Check plugin's prerequisites before installation
 */
function webhook_check_prerequisites() {
   if (version_compare(ITSM_VERSION, WEBHOOK_ITSMNG_MIN_VERSION, 'lt')) {
      echo "This plugin requires ITSM >= " . WEBHOOK_ITSMNG_MIN_VERSION . "<br>";
      return false;
   }

   if (!is_readable(__DIR__ . '/../../vendor/autoload.php') || !is_file(__DIR__ . '/../../vendor/autoload.php')) {
      echo "Run composer install --no-dev in the root directory so the host autoloader is available<br>";
      return false;
   }

   return true;
}

/**
 * Check plugin's config before activation (if needed)
 */
function webhook_check_config($verbose = false) {
   if ($verbose) {
      echo "Checking plugin configuration<br>";
   }
   return true;
}
