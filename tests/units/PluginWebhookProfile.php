<?php

namespace tests\units;

use GlpiPlugin\Webhook\NotificationWebhookSetting;
use GlpiPlugin\Webhook\Profile;
use GlpiPlugin\Webhook\WebhookMenu;

class PluginWebhookProfile extends \DbTestCase
{
   public function testRightsMenuAndSettingsMetadata()
   {
      $rights = Profile::getRightsGeneral();

      $this->array($rights)->hasSize(2);
      $this->string($rights[0]['field'])->isIdenticalTo('plugin_webhook_config');
      $this->string($rights[1]['field'])->isIdenticalTo('plugin_webhook_use');

      $_SESSION['glpiactiveprofile']['plugin_webhook_config'] = UPDATE;
      $_SESSION['glpiactiveprofile']['plugin_webhook_use'] = READ;

      $menu = WebhookMenu::getMenuContent();

      $this->array($menu)->hasKeys(['title', 'page', 'icon', 'options']);
      $this->array($menu['options'])->hasKeys(['webhook', 'template', 'notification', 'config']);
      $this->string(NotificationWebhookSetting::getMode())->isIdenticalTo('webhook');
      $this->string((new NotificationWebhookSetting())->getEnableLabel())->isNotEmpty();
   }

   public function testDefaultProfileRightsAreInstalled()
   {
      $this->integer(countElementsInTable('glpi_profilerights', [
         'profiles_id' => Profile::SUPER_ADMIN_PROFILE_ID,
         'name' => 'plugin_webhook_config',
      ]))->isGreaterThanOrEqualTo(1);
      $this->integer(countElementsInTable('glpi_profilerights', [
         'profiles_id' => Profile::SUPER_ADMIN_PROFILE_ID,
         'name' => 'plugin_webhook_use',
      ]))->isGreaterThanOrEqualTo(1);
   }
}
