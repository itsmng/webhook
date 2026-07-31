<?php

namespace tests\units;

use GlpiPlugin\Webhook\Config;
use GlpiPlugin\Webhook\Notification;
use GlpiPlugin\Webhook\Profile;
use GlpiPlugin\Webhook\Template;
use GlpiPlugin\Webhook\TemplateTranslation;
use GlpiPlugin\Webhook\UserWebhook;
use GlpiPlugin\Webhook\Webhook;
use GlpiPlugin\Webhook\WebhookTemplate;

class PluginWebhookConfig extends \DbTestCase
{
   public function testPluginMetadataAndPrerequisites()
   {
      $version = \plugin_version_webhook();

      $this->string($version['name'])->isIdenticalTo('Webhook Plugin');
      $this->string($version['version'])->isIdenticalTo(WEBHOOK_VERSION);
      $this->string($version['homepage'])->isIdenticalTo('https://github.com/itsmng/plugin-webhook');
      $this->boolean(\webhook_check_prerequisites())->isTrue();
      $this->boolean(\webhook_check_config())->isTrue();
   }

   public function testPluginInitRegistersHooksAndLegacyAliases()
   {
      global $CFG_GLPI, $PLUGIN_HOOKS;

      $PLUGIN_HOOKS = [];
      $_SESSION['glpiactiveprofile']['plugin_webhook_config'] = UPDATE;
      Config::setValues(['notifications_webhook' => '1']);

      \plugin_init_webhook();

      $this->boolean($PLUGIN_HOOKS['csrf_compliant']['webhook'])->isTrue();
      $this->array($PLUGIN_HOOKS['add_javascript']['webhook'])
         ->contains('/plugins/webhook/js/webhook-headers.js');
      $this->string($PLUGIN_HOOKS['menu_toadd']['webhook']['config'])
         ->isIdenticalTo(\GlpiPlugin\Webhook\WebhookMenu::class);
      $this->string($PLUGIN_HOOKS['config_page']['webhook'])
         ->isIdenticalTo('front/config.form.php');
      $this->integer($CFG_GLPI['notifications_webhook'])->isIdenticalTo(1);
      $this->object(new \PluginWebhookWebhook())->isInstanceOf(Webhook::class);
      $this->object(new \PluginWebhookTemplateTranslation())->isInstanceOf(TemplateTranslation::class);
      $this->object(new \PluginWebhookNotificationWebhook())->isInstanceOf(\GlpiPlugin\Webhook\NotificationWebhook::class);
   }

   public function testInstallCreatesTablesAndDefaultConfig()
   {
      global $DB;

      foreach (
         [
            Config::getTable(),
            Profile::getTable(),
            Webhook::getTable(),
            Template::getTable(),
            TemplateTranslation::getTable(),
            WebhookTemplate::getTable(),
            Notification::getTable(),
            UserWebhook::getTable(),
         ] as $table
      ) {
         $this->boolean($DB->tableExists($table))->isTrue($table . ' should exist');
      }

      $this->string(Config::getValue('notifications_webhook'))->isIdenticalTo('1');
      $this->string(Config::getValue('webhook_default_timeout'))->isIdenticalTo('5');
      $this->string(Config::getValue('webhook_verify_ssl'))->isIdenticalTo('1');
   }

   public function testValuesCanBeUpdatedAndInserted()
   {
      global $CFG_GLPI;

      Config::setValues([
         'notifications_webhook' => '0',
         'webhook_default_timeout' => '17',
         'custom_webhook_setting' => 'custom value',
      ]);

      $this->string(Config::getValue('notifications_webhook'))->isIdenticalTo('0');
      $this->integer($CFG_GLPI['notifications_webhook'])->isIdenticalTo(0);
      $this->string(Config::getValue('webhook_default_timeout'))->isIdenticalTo('17');
      $this->string(Config::getValue('custom_webhook_setting'))->isIdenticalTo('custom value');
      $this->string(Config::getValue('missing_setting', 'fallback'))->isIdenticalTo('fallback');
   }
}
