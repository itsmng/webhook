<?php

namespace tests\units;

use GlpiPlugin\Webhook\UserWebhook;
use GlpiPlugin\Webhook\Webhook;

class PluginWebhookUserWebhook extends \DbTestCase
{
   public function testLookupCanFilterInactiveRelations()
   {
      $userId = getItemByTypeName('User', TU_USER, true);
      $activeWebhookId = $this->addWebhook('User active webhook');
      $inactiveWebhookId = $this->addWebhook('User inactive webhook');

      $relation = new UserWebhook();
      $this->integer((int)$relation->add([
         'users_id' => $userId,
         'plugin_webhook_webhooks_id' => $activeWebhookId,
         'is_active' => 1,
      ]))->isGreaterThan(0);
      $this->integer((int)$relation->add([
         'users_id' => $userId,
         'plugin_webhook_webhooks_id' => $inactiveWebhookId,
         'is_active' => 0,
      ]))->isGreaterThan(0);

      $this->array(UserWebhook::getWebhooksForUser($userId))->isIdenticalTo([$activeWebhookId]);
      $this->array(UserWebhook::getWebhooksForUser($userId, false))
         ->isIdenticalTo([$activeWebhookId, $inactiveWebhookId]);
   }

   private function addWebhook(string $name): int
   {
      $webhook = new Webhook();
      $id = $webhook->add([
         'name' => $name,
         'url' => 'https://example.com/' . strtolower(str_replace(' ', '-', $name)),
         'http_method' => 'POST',
         'headers' => '{}',
         'is_active' => 1,
         'timeout' => 5,
         'verify_ssl' => 1,
         'entities_id' => 0,
         'is_recursive' => 1,
      ]);

      $this->integer((int)$id)->isGreaterThan(0);

      return (int)$id;
   }
}
