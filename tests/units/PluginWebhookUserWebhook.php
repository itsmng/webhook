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

   public function testAssignableWebhookConditionUsesActiveEntityInsteadOfUserEntity()
   {
      global $DB;

      $this->login();
      $this->setEntity('_test_child_1', false);

      $parentId = (int)getItemByTypeName('Entity', '_test_root_entity', true);
      $childId = (int)getItemByTypeName('Entity', '_test_child_1', true);
      $recursiveParentId = $this->addWebhook('Recursive parent webhook', $parentId, 1);
      $childIdWebhook = $this->addWebhook('Child webhook', $childId, 0);
      $this->addWebhook('Inactive child webhook', $childId, 0, 0);

      $rows = iterator_to_array($DB->request([
         'SELECT' => ['id'],
         'FROM' => Webhook::getTable(),
         'WHERE' => UserWebhook::getAssignableWebhookCondition(),
         'ORDER' => 'id ASC',
      ]));
      $ids = array_map('intval', array_column($rows, 'id'));

      $this->array($ids)->contains($recursiveParentId);
      $this->array($ids)->contains($childIdWebhook);
      $this->integer(count($ids))->isIdenticalTo(2);
   }

   private function addWebhook(
      string $name,
      int $entitiesId = 0,
      int $isRecursive = 1,
      int $isActive = 1
   ): int
   {
      $webhook = new Webhook();
      $id = $webhook->add([
         'name' => $name,
         'url' => 'https://example.com/' . strtolower(str_replace(' ', '-', $name)),
         'http_method' => 'POST',
         'headers' => '{}',
         'is_active' => $isActive,
         'timeout' => 5,
         'verify_ssl' => 1,
         'entities_id' => $entitiesId,
         'is_recursive' => $isRecursive,
      ]);

      $this->integer((int)$id)->isGreaterThan(0);

      return (int)$id;
   }
}
